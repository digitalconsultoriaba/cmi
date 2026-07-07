<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\CourtesyVoucher;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketStatus;
use App\Domain\Events\Models\TicketType;
use App\Models\User;

/**
 * Cortesias: regra X→Y com limite por conta + resgate de voucher.
 * SEMPRE chamado dentro da transação/lock do TicketPurchaseService
 * (recontagens protegidas contra corrida — constituição, II).
 */
class CourtesyResolver
{
    /** Quantas cortesias automáticas este pedido concede (FR-009). */
    public function automaticGrants(Event $event, User $buyer, int $paidCount): int
    {
        if (! $event->allow_courtesy
            || $event->courtesy_paid_threshold === null
            || $paidCount < $event->courtesy_paid_threshold
            || $this->courtesyType($event) === null) {
            return 0;
        }

        $grants = intdiv($paidCount, $event->courtesy_paid_threshold)
            * max(1, (int) $event->courtesy_grant_per_threshold);

        if ($event->courtesy_limit_per_account !== null) {
            // Recontagem sob o lock: cortesias vivas já obtidas pelo comprador
            $already = Ticket::query()
                ->where('event_id', $event->id)
                ->where('is_courtesy', true)
                ->whereIn('status_id', TicketStatus::idsFor(TicketStatus::COUNTS_CAPACITY))
                ->whereHas('order', fn ($q) => $q->where('buyer_user_id', $buyer->id))
                ->count();

            $grants = max(0, min($grants, $event->courtesy_limit_per_account - $already));
        }

        return $grants;
    }

    /** Tipo de cortesia do evento (necessário para emitir o ingresso). */
    public function courtesyType(Event $event): ?TicketType
    {
        return $event->ticketTypes()
            ->where('is_courtesy', true)
            ->where('is_active', true)
            ->orderBy('sort')
            ->first();
    }

    // ── Voucher por participante no checkout do seminário (spec 014) ──────

    /**
     * Localiza um voucher resgatável (available OU distributed) sob lock,
     * validando pertencimento ao evento, não-uso e elegibilidade ao tipo.
     * Lança DomainRuleViolation se inválido (spec 014, research R4).
     */
    public function findRedeemable(Event $event, string $code, ?int $ticketTypeId = null): CourtesyVoucher
    {
        $voucher = CourtesyVoucher::query()
            ->where('event_id', $event->id)
            ->where('code', trim($code))
            ->lockForUpdate()
            ->first();

        if ($voucher === null
            || ! in_array($voucher->status, [CourtesyVoucher::AVAILABLE, CourtesyVoucher::DISTRIBUTED], true)) {
            throw new DomainRuleViolation(
                'Voucher inválido, expirado ou já utilizado. Verifique o código informado.',
                'invalid_voucher'
            );
        }

        // Elegibilidade ao tipo: voucher vinculado a um tipo só serve àquele tipo.
        if ($voucher->ticket_type_id !== null && $ticketTypeId !== null
            && $voucher->ticket_type_id !== $ticketTypeId) {
            throw new DomainRuleViolation(
                'Este voucher não é válido para o tipo de ingresso selecionado.',
                'invalid_voucher'
            );
        }

        return $voucher;
    }

    /** Validação read-only (sem lock/resgate) para a UI aplicar por inscrição. */
    public function isRedeemable(Event $event, string $code, ?int $ticketTypeId = null): bool
    {
        $voucher = CourtesyVoucher::query()
            ->where('event_id', $event->id)
            ->where('code', trim($code))
            ->first();

        if ($voucher === null
            || ! in_array($voucher->status, [CourtesyVoucher::AVAILABLE, CourtesyVoucher::DISTRIBUTED], true)) {
            return false;
        }

        return ! ($voucher->ticket_type_id !== null && $ticketTypeId !== null
            && $voucher->ticket_type_id !== $ticketTypeId);
    }

    /** Marca o voucher como resgatado, ligado ao ingresso criado no pedido. */
    public function markRedeemed(CourtesyVoucher $voucher, Ticket $ticket, User $buyer): void
    {
        $voucher->forceFill([
            'redeemed_at' => now(),
            'redeemed_ticket_id' => $ticket->id,
        ]);
        $voucher->transitionTo(CourtesyVoucher::REDEEMED);

        activity('courtesy.redeemed')
            ->performedOn($ticket)
            ->causedBy($buyer)
            ->withProperties(['reference' => $ticket->code, 'voucher' => $voucher->code])
            ->log('Voucher '.$voucher->code.' resgatado → ingresso '.$ticket->code);
    }

    /**
     * Resgate de voucher: pedido PRÓPRIO de total zero (nasce pago) — a
     * expiração de um pedido pago nunca desfaz o resgate (research, Decisão 2).
     */
    public function redeemVoucher(Event $event, User $buyer, string $code, array $participant): Order
    {
        $voucher = CourtesyVoucher::query()
            ->where('event_id', $event->id)
            ->where('code', trim($code))
            ->lockForUpdate()
            ->first();

        if ($voucher === null || $voucher->status !== CourtesyVoucher::DISTRIBUTED) {
            throw new DomainRuleViolation(
                'Voucher inválido, não distribuído ou já resgatado.',
                'invalid_voucher'
            );
        }

        $type = $voucher->ticket_type_id !== null
            ? $event->ticketTypes()->whereKey($voucher->ticket_type_id)->first()
            : $this->courtesyType($event);

        if ($type === null) {
            throw new DomainRuleViolation(
                'O evento não possui tipo de cortesia configurado.',
                'invalid_voucher'
            );
        }

        $order = $event->orders()->create([
            'buyer_user_id' => $buyer->id,
            'buyer_name' => $buyer->name,
            'buyer_email' => $buyer->email,
            'buyer_document' => $buyer->document,
            'total_amount' => '0.00',
            'status_id' => OrderStatus::idFor(OrderStatus::PAID), // total 0 → pago
        ]);

        $ticket = $order->tickets()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $type->id,
            'participant_name' => $participant['participant_name'] ?? $buyer->name,
            'participant_email' => isset($participant['participant_email'])
                ? mb_strtolower(trim($participant['participant_email'])) : $buyer->email,
            'participant_user_id' => ($participant['participant_email'] ?? null) ? null : $buyer->id,
            'unit_price' => '0.00',
            'is_courtesy' => true,
            'status_id' => TicketStatus::idFor(TicketStatus::COURTESY),
        ]);

        $voucher->forceFill([
            'redeemed_at' => now(),
            'redeemed_ticket_id' => $ticket->id,
        ]);
        $voucher->transitionTo(CourtesyVoucher::REDEEMED);

        activity('courtesy.redeemed')
            ->performedOn($ticket)
            ->causedBy($buyer)
            ->withProperties(['reference' => $ticket->code, 'voucher' => $voucher->code])
            ->log('Voucher '.$voucher->code.' resgatado → ingresso '.$ticket->code);

        return $order->load('tickets');
    }
}
