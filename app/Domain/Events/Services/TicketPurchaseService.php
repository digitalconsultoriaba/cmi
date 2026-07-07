<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventShirtSize;
use App\Domain\Events\Models\EventStatus;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketLot;
use App\Domain\Events\Models\TicketStatus;
use App\Domain\Events\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Compra em transação única com lock no evento + recontagem total
 * (constituição, princípio II; research 004, Decisão 1). Zero overselling.
 */
class TicketPurchaseService
{
    public function __construct(
        private readonly CourtesyResolver $courtesy,
        private readonly GuestBuyerService $guestBuyer,
    ) {
    }

    /**
     * @param  array  $items  itens do carrinho (participante por item)
     * @param  array  $courtesyParticipants  dados p/ cortesias automáticas
     * @return Order[] pedido do carrinho e/ou pedido do voucher
     */
    public function purchase(
        Event $event,
        User $buyer,
        array $items,
        array $courtesyParticipants = [],
        ?string $voucherCode = null,
    ): array {
        return DB::transaction(function () use ($event, $buyer, $items, $courtesyParticipants, $voucherCode) {
            // Mutex por evento: serializa compras concorrentes (InnoDB)
            $event = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            $orders = [];

            if ($items !== []) {
                $orders[] = $this->createCartOrder($event, $buyer, $items, $courtesyParticipants);
            }

            if ($voucherCode !== null && $voucherCode !== '') {
                $orders[] = $this->courtesy->redeemVoucher(
                    $event, $buyer, $voucherCode, $courtesyParticipants[0] ?? []
                );
            }

            return $orders;
        });
    }

    /**
     * Checkout do seminário (spec 014): pedido ÚNICO e MISTO — itens com
     * voucher viram cortesia (R$0) dentro do mesmo pedido das pagas; total
     * derivado; snapshot de categoria/campos; contas de participante por e-mail.
     */
    public function purchaseSeminar(Event $event, User $buyer, array $items, bool $linkParticipants = true): Order
    {
        return DB::transaction(function () use ($event, $buyer, $items, $linkParticipants) {
            $event = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $this->ensureSellable($event);

            $types = $event->ticketTypes()->where('is_active', true)->get()->keyBy('id');
            $sizes = $event->shirtSizes()->where('is_active', true)->get()->keyBy('id');

            $resolved = [];
            $seatsNeeded = 0;
            $lotDemand = [];
            $sizeDemand = [];
            $typeDemand = [];
            $usedVouchers = [];

            foreach ($items as $index => $item) {
                $type = $types->get($item['ticket_type_id'] ?? null)
                    ?? throw ValidationException::withMessages([
                        "items.$index.ticket_type_id" => 'Tipo de ingresso inválido para este evento.',
                    ]);

                $lot = $event->currentLot($type);
                if ($lot === null) {
                    throw new DomainRuleViolation('As vendas não estão abertas para este ingresso.', 'sales_closed');
                }

                $this->validateShirts($event, $type, $item, $index, $sizes, $sizeDemand);

                $seats = max((int) $type->seats_per_ticket, $type->is_couple ? 2 : 1);
                $seatsNeeded += $seats;
                $typeDemand[$type->id] = ($typeDemand[$type->id] ?? 0) + 1;
                $lotDemand[$lot->id] = ($lotDemand[$lot->id] ?? 0) + 1;

                // Voucher por inscrição → cortesia (R$0) no mesmo pedido.
                $voucher = null;
                $code = trim((string) ($item['voucher_code'] ?? ''));
                if ($code !== '') {
                    if (isset($usedVouchers[$code])) {
                        throw new DomainRuleViolation('Este voucher já foi aplicado em outra inscrição.', 'voucher_reused');
                    }
                    $voucher = $this->courtesy->findRedeemable($event, $code, $type->id);
                    $usedVouchers[$code] = true;
                }

                $resolved[] = [
                    'item' => $item,
                    'type' => $type,
                    'lot' => $lot,
                    'price' => $voucher !== null ? '0.00' : ($lot->effectivePrice($type) ?? $type->price),
                    'voucher' => $voucher,
                ];
            }

            $this->assertCapacity($event, $seatsNeeded, $typeDemand, $types, $lotDemand, $sizeDemand, $sizes);

            $total = collect($resolved)->sum(fn ($r) => (float) $r['price']);
            $isFree = $total <= 0;

            $order = $event->orders()->create([
                'buyer_user_id' => $buyer->id,
                'buyer_name' => $buyer->name,
                'buyer_email' => $buyer->email,
                'buyer_document' => $buyer->document,
                'total_amount' => number_format($total, 2, '.', ''),
                'status_id' => OrderStatus::idFor($isFree ? OrderStatus::PAID : OrderStatus::PENDING),
                'reserved_until' => $isFree ? null
                    : Carbon::now()->addMinutes((int) $event->reservation_ttl_minutes),
            ]);

            foreach ($resolved as $r) {
                $item = $r['item'];
                $email = isset($item['participant_email']) ? mb_strtolower(trim($item['participant_email'])) : null;

                $participantUserId = null;
                if ($linkParticipants && $email !== null) {
                    $participantUserId = $this->guestBuyer->resolveParticipant($email, $item['participant_name'])->id;
                } elseif ($email !== null && $email === $buyer->email) {
                    $participantUserId = $buyer->id;
                }

                $ticket = $order->tickets()->create([
                    'event_id' => $event->id,
                    'ticket_type_id' => $r['type']->id,
                    'ticket_lot_id' => $r['lot']->id,
                    'participant_name' => $item['participant_name'],
                    'participant_email' => $email,
                    'participant_document' => $item['participant_document'] ?? null,
                    'participant_user_id' => $participantUserId,
                    'participant_category_key' => $item['category_key'] ?? null,
                    'participant_fields' => $item['fields'] ?? null,
                    'companion_name' => $item['companion_name'] ?? null,
                    'companion_shirt_model_id' => $item['companion_shirt_model_id'] ?? null,
                    'companion_shirt_size_id' => $item['companion_shirt_size_id'] ?? null,
                    'shirt_model_id' => $item['shirt_model_id'] ?? null,
                    'shirt_size_id' => $item['shirt_size_id'] ?? null,
                    'unit_price' => $r['price'],
                    'is_courtesy' => $r['voucher'] !== null,
                    'status_id' => TicketStatus::idFor(
                        $r['voucher'] !== null ? TicketStatus::COURTESY
                            : ($isFree ? TicketStatus::CONFIRMED : TicketStatus::RESERVED)
                    ),
                ]);

                if ($r['voucher'] !== null) {
                    $this->courtesy->markRedeemed($r['voucher'], $ticket, $buyer);
                }
            }

            foreach (array_keys($lotDemand) as $lotId) {
                TicketLot::query()->whereKey($lotId)->first()->recountSold();
            }
            foreach (array_keys($sizeDemand) as $sizeId) {
                EventShirtSize::query()->whereKey($sizeId)->first()->recountSold();
            }

            return $order->load('tickets.status', 'tickets.ticketType', 'event');
        });
    }

    /** Recontagens sob o lock: capacidade, tipo, lote e estoque de camisa. */
    private function assertCapacity(
        Event $event, int $seatsNeeded, array $typeDemand, $types,
        array $lotDemand, array $sizeDemand, $sizes,
    ): void {
        if ($event->total_capacity !== null
            && $event->ticketsSold() + $seatsNeeded > $event->total_capacity) {
            throw new DomainRuleViolation('Não há vagas suficientes para este pedido.', 'sold_out');
        }

        foreach ($typeDemand as $typeId => $demand) {
            $available = $types[$typeId]->available();
            if ($available !== null && $demand > $available) {
                throw new DomainRuleViolation(
                    "Ingressos esgotados para o tipo \"{$types[$typeId]->name}\".", 'sold_out'
                );
            }
        }

        foreach ($lotDemand as $lotId => $demand) {
            $lot = TicketLot::query()->whereKey($lotId)->first();
            if ($lot->quantity !== null) {
                $liveInLot = $lot->tickets()
                    ->whereIn('status_id', TicketStatus::idsFor(TicketStatus::COUNTS_CAPACITY))
                    ->count();
                if ($liveInLot + $demand > $lot->quantity) {
                    throw new DomainRuleViolation(
                        "O lote \"{$lot->name}\" não tem saldo para este pedido.", 'sold_out'
                    );
                }
            }
        }

        foreach ($sizeDemand as $sizeId => $demand) {
            $size = $sizes[$sizeId];
            if ($size->stock_quantity !== null) {
                $size->recountSold();
                if ($size->fresh()->sold_count + $demand > $size->stock_quantity) {
                    throw new DomainRuleViolation(
                        "Estoque esgotado para a camisa \"{$size->label}\".", 'sold_out'
                    );
                }
            }
        }
    }

    private function createCartOrder(Event $event, User $buyer, array $items, array $courtesyParticipants): Order
    {
        $this->ensureSellable($event);

        $types = $event->ticketTypes()->where('is_active', true)->get()->keyBy('id');
        $sizes = $event->shirtSizes()->where('is_active', true)->get()->keyBy('id');

        // ── Resolver itens: tipo, lote vigente, preço efetivo, assentos ──
        $resolved = [];
        $seatsNeeded = 0;
        $lotDemand = [];   // lot_id => qtd
        $sizeDemand = [];  // size_id => qtd (titular + acompanhante)
        $typeDemand = [];  // type_id => qtd

        foreach ($items as $index => $item) {
            $type = $types->get($item['ticket_type_id'] ?? null)
                ?? throw ValidationException::withMessages([
                    "items.$index.ticket_type_id" => 'Tipo de ingresso inválido para este evento.',
                ]);

            $lot = $event->currentLot($type);
            if ($lot === null) {
                throw new DomainRuleViolation('As vendas não estão abertas para este ingresso.', 'sales_closed');
            }

            $this->validateShirts($event, $type, $item, $index, $sizes, $sizeDemand);

            $seats = max((int) $type->seats_per_ticket, $type->is_couple ? 2 : 1);
            $seatsNeeded += $seats;
            $typeDemand[$type->id] = ($typeDemand[$type->id] ?? 0) + 1;
            $lotDemand[$lot->id] = ($lotDemand[$lot->id] ?? 0) + 1;

            $resolved[] = [
                'item' => $item,
                'type' => $type,
                'lot' => $lot,
                'price' => $lot->effectivePrice($type) ?? $type->price,
            ];
        }

        // ── Cortesias automáticas (ocupam vaga — FR-009) ──
        $paidCount = count($resolved);
        $grants = $this->courtesy->automaticGrants($event, $buyer, $paidCount);
        $courtesyType = $grants > 0 ? $this->courtesy->courtesyType($event) : null;
        $seatsNeeded += $grants; // cortesia = 1 assento

        // ── Recontagens sob o lock (fonte de verdade, nunca cache) ──
        if ($event->total_capacity !== null
            && $event->ticketsSold() + $seatsNeeded > $event->total_capacity) {
            throw new DomainRuleViolation('Não há vagas suficientes para este pedido.', 'sold_out');
        }

        foreach ($typeDemand as $typeId => $demand) {
            $available = $types[$typeId]->available();
            if ($available !== null && $demand > $available) {
                throw new DomainRuleViolation(
                    "Ingressos esgotados para o tipo \"{$types[$typeId]->name}\".", 'sold_out'
                );
            }
        }

        foreach ($lotDemand as $lotId => $demand) {
            $lot = TicketLot::query()->whereKey($lotId)->first();
            if ($lot->quantity !== null) {
                $liveInLot = $lot->tickets()
                    ->whereIn('status_id', TicketStatus::idsFor(TicketStatus::COUNTS_CAPACITY))
                    ->count();
                if ($liveInLot + $demand > $lot->quantity) {
                    throw new DomainRuleViolation(
                        "O lote \"{$lot->name}\" não tem saldo para este pedido.", 'sold_out'
                    );
                }
            }
        }

        foreach ($sizeDemand as $sizeId => $demand) {
            $size = $sizes[$sizeId];
            if ($size->stock_quantity !== null) {
                $size->recountSold();
                if ($size->fresh()->sold_count + $demand > $size->stock_quantity) {
                    throw new DomainRuleViolation(
                        "Estoque esgotado para a camisa \"{$size->label}\".", 'sold_out'
                    );
                }
            }
        }

        // ── Criar pedido + ingressos (snapshot) ──
        $total = collect($resolved)->sum(fn ($r) => (float) $r['price']);
        $isFree = $total <= 0;

        $order = $event->orders()->create([
            'buyer_user_id' => $buyer->id,
            'buyer_name' => $buyer->name,
            'buyer_email' => $buyer->email,
            'buyer_document' => $buyer->document,
            'total_amount' => number_format($total, 2, '.', ''),
            'status_id' => OrderStatus::idFor($isFree ? OrderStatus::PAID : OrderStatus::PENDING),
            'reserved_until' => $isFree ? null
                : Carbon::now()->addMinutes((int) $event->reservation_ttl_minutes),
        ]);

        foreach ($resolved as $r) {
            $item = $r['item'];
            $email = isset($item['participant_email'])
                ? mb_strtolower(trim($item['participant_email'])) : null;

            $order->tickets()->create([
                'event_id' => $event->id,
                'ticket_type_id' => $r['type']->id,
                'ticket_lot_id' => $r['lot']->id,
                'participant_name' => $item['participant_name'],
                'participant_email' => $email,
                'participant_document' => $item['participant_document'] ?? null,
                'participant_user_id' => $email === $buyer->email ? $buyer->id : null,
                'companion_name' => $item['companion_name'] ?? null,
                'companion_shirt_model_id' => $item['companion_shirt_model_id'] ?? null,
                'companion_shirt_size_id' => $item['companion_shirt_size_id'] ?? null,
                'shirt_model_id' => $item['shirt_model_id'] ?? null,
                'shirt_size_id' => $item['shirt_size_id'] ?? null,
                'unit_price' => $r['price'], // snapshot do momento da compra
                'status_id' => TicketStatus::idFor(
                    $isFree ? TicketStatus::CONFIRMED : TicketStatus::RESERVED
                ),
            ]);
        }

        for ($i = 1; $i <= $grants; $i++) {
            $participant = $courtesyParticipants[$i - 1] ?? [];
            $order->tickets()->create([
                'event_id' => $event->id,
                'ticket_type_id' => $courtesyType->id,
                'participant_name' => $participant['participant_name'] ?? $buyer->name,
                'participant_email' => isset($participant['participant_email'])
                    ? mb_strtolower(trim($participant['participant_email'])) : null,
                'unit_price' => '0.00',
                'is_courtesy' => true,
                'status_id' => TicketStatus::idFor(TicketStatus::COURTESY),
            ]);
        }

        // ── Caches coerentes na mesma transação ──
        foreach (array_keys($lotDemand) as $lotId) {
            TicketLot::query()->whereKey($lotId)->first()->recountSold();
        }
        foreach (array_keys($sizeDemand) as $sizeId) {
            EventShirtSize::query()->whereKey($sizeId)->first()->recountSold();
        }

        return $order->load('tickets');
    }

    private function ensureSellable(Event $event): void
    {
        $now = Carbon::now();

        $closed = $event->status?->slug !== EventStatus::PUBLISHED
            || ($event->sales_start_at !== null && $now->lt($event->sales_start_at))
            || ($event->sales_end_at !== null && $now->gt($event->sales_end_at));

        if ($closed) {
            throw new DomainRuleViolation('As inscrições não estão abertas.', 'sales_closed');
        }
    }

    private function validateShirts(
        Event $event,
        TicketType $type,
        array $item,
        int $index,
        $sizes,
        array &$sizeDemand,
    ): void {
        $checkPair = function (?int $modelId, ?int $sizeId, string $field) use ($event, $index, $sizes, &$sizeDemand) {
            if ($sizeId === null && $modelId === null) {
                return;
            }
            $size = $sizes->get($sizeId);
            if ($size === null || $size->shirt_model_id !== $modelId) {
                throw ValidationException::withMessages([
                    "items.$index.$field" => 'Camisa inválida para este evento.',
                ]);
            }
            $sizeDemand[$sizeId] = ($sizeDemand[$sizeId] ?? 0) + 1;
        };

        if ($event->requires_shirt && ($item['shirt_size_id'] ?? null) === null) {
            throw ValidationException::withMessages([
                "items.$index.shirt_size_id" => 'Escolha a camisa deste participante.',
            ]);
        }

        $checkPair($item['shirt_model_id'] ?? null, $item['shirt_size_id'] ?? null, 'shirt_size_id');

        if ($type->is_couple) {
            if (blank($item['companion_name'] ?? null)) {
                throw ValidationException::withMessages([
                    "items.$index.companion_name" => 'Informe o nome do acompanhante.',
                ]);
            }
            if ($event->requires_shirt && ($item['companion_shirt_size_id'] ?? null) === null) {
                throw ValidationException::withMessages([
                    "items.$index.companion_shirt_size_id" => 'Escolha a camisa do acompanhante.',
                ]);
            }
            $checkPair(
                $item['companion_shirt_model_id'] ?? null,
                $item['companion_shirt_size_id'] ?? null,
                'companion_shirt_size_id'
            );
        }
    }
}
