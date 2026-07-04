<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\CourtesyVoucher;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\Payment;
use App\Domain\Events\Models\PaymentStatus;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketStatus;

/**
 * Fórmulas canônicas do painel (spec 008) — TUDO derivado na consulta
 * (princípio II / FR-012). Telas e planilhas consomem ESTE service:
 * divergência tela×export é impossível por construção.
 */
class ReportService
{
    /** Situações que valem entrada — mesma régua da portaria (FR-007). */
    public const ELIGIBLE = [
        TicketStatus::PAID, TicketStatus::CONFIRMED, TicketStatus::COURTESY, TicketStatus::USED,
    ];

    public function dashboard(Event $event): array
    {
        $tickets = $this->eligibleTickets($event);

        $people = (int) $tickets->sum(fn (Ticket $ticket) => $this->seats($ticket));
        $present = (int) $tickets
            ->filter(fn (Ticket $ticket) => $ticket->status?->slug === TicketStatus::USED)
            ->sum(fn (Ticket $ticket) => $this->seats($ticket));

        return [
            'event' => [
                'name' => $event->name,
                'startsAt' => $event->starts_at?->toISOString(),
                'capacity' => $event->total_capacity,
            ],
            'people' => [
                'confirmed' => $people,
                'capacity' => $event->total_capacity,
                'present' => $present,
                'absent' => $people - $present,
            ],
            'ticketsByStatus' => $this->ticketsByStatus($event),
            'revenue' => $this->revenue($event),
            'shirts' => $this->shirtGrid($tickets, $people),
            'byLot' => $this->byLot($event, $tickets),
            'byMethod' => $this->byMethod($event),
            'courtesies' => $this->courtesies($event, $tickets),
        ];
    }

    /** Ingressos elegíveis com tudo que as derivações precisam, numa consulta. */
    public function eligibleTickets(Event $event)
    {
        return Ticket::query()
            ->where('event_id', $event->id)
            ->whereIn('status_id', TicketStatus::idsFor(self::ELIGIBLE))
            ->with([
                'ticketType', 'status', 'ticketLot',
                'shirtModel', 'shirtSize', 'companionShirtModel', 'companionShirtSize',
            ])
            ->orderBy('participant_name')
            ->get();
    }

    public function seats(Ticket $ticket): int
    {
        $type = $ticket->ticketType;

        return max((int) ($type?->seats_per_ticket ?? 1), $type?->is_couple ? 2 : 1);
    }

    /**
     * Consolidado financeiro — visão de CAIXA do período: o que entrou
     * (paid_at no intervalo, mesmo que estornado depois) e o que saiu
     * (refunded_at no intervalo). Período em UTC, já convertido do fuso do
     * evento pelo controller (FR-011).
     */
    public function finance(Event $event, ?\Illuminate\Support\Carbon $from, ?\Illuminate\Support\Carbon $to): array
    {
        $received = $this->paymentsInPeriod($event, $from, $to);

        $byMethod = (clone $received)
            ->selectRaw('method, COUNT(*) as total, SUM(amount) as amount')
            ->groupBy('method')
            ->orderBy('method')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->method,
                'label' => self::METHOD_LABELS[$row->method] ?? $row->method,
                'count' => (int) $row->total,
                'amount' => $this->money((string) $row->amount),
            ])->values();

        $totalAmount = $byMethod->reduce(fn ($c, $r) => bcadd($c, $r['amount'], 2), '0.00');
        $totalCount = (int) $byMethod->sum('count');

        $refunds = $this->refundsInPeriod($event, $from, $to);

        $refundAmount = $this->money((string) (clone $refunds)->sum('refund_amount'));
        $refundCount = (clone $refunds)->count();

        // Fotografias atuais (sem filtro): pendências e posição de patrocínios
        $pendingOrders = $event->orders()
            ->whereIn('status_id', OrderStatus::idsFor([OrderStatus::PENDING]));

        $installments = \App\Domain\Events\Models\SponsorshipInstallment::query()
            ->whereHas('sponsorship', fn ($q) => $q
                ->where('event_id', $event->id)->where('status', '!=', 'cancelled'));

        $overdue = (clone $installments)
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());

        return [
            'period' => ($from || $to) ? [
                'from' => $from?->toISOString(),
                'to' => $to?->toISOString(),
            ] : null,
            'byMethod' => $byMethod->all(),
            'total' => ['count' => $totalCount, 'amount' => $this->money($totalAmount)],
            'refunds' => ['count' => $refundCount, 'amount' => $refundAmount],
            'net' => $this->money(bcsub($totalAmount, $refundAmount, 2)),
            'pendingOrders' => [
                'count' => (clone $pendingOrders)->count(),
                'amount' => $this->money((string) (clone $pendingOrders)->sum('total_amount')),
            ],
            'sponsorships' => [
                'received' => $this->money((string) (clone $installments)
                    ->where('status', 'paid')->sum('paid_amount')),
                'receivable' => $this->money((string) (clone $installments)
                    ->where('status', 'pending')->sum('amount')),
                'overdue' => [
                    'count' => (clone $overdue)->count(),
                    'amount' => $this->money((string) (clone $overdue)->sum('amount')),
                ],
            ],
        ];
    }

    public const METHOD_LABELS = [
        'pix' => 'Pix', 'boleto' => 'Boleto', 'card' => 'Cartão', 'manual' => 'Manual',
    ];

    /**
     * Entradas do caixa no período: pagamentos com baixa (paid_at), ainda
     * confirmados OU estornados depois — o dinheiro ENTROU; a saída é refund.
     * Base única de tela E planilha (invariante 5).
     */
    public function paymentsInPeriod(Event $event, ?\Illuminate\Support\Carbon $from, ?\Illuminate\Support\Carbon $to)
    {
        return Payment::query()
            ->whereHas('order', fn ($q) => $q->where('event_id', $event->id))
            ->whereIn('status_id', PaymentStatus::idsFor([PaymentStatus::PAID, PaymentStatus::REFUNDED]))
            ->whereNotNull('paid_at')
            ->tap(fn ($q) => $this->betweenPeriod($q, 'paid_at', $from, $to));
    }

    /** Saídas do caixa no período: devoluções registradas nos ingressos. */
    public function refundsInPeriod(Event $event, ?\Illuminate\Support\Carbon $from, ?\Illuminate\Support\Carbon $to)
    {
        return Ticket::query()
            ->where('event_id', $event->id)
            ->whereNotNull('refunded_at')
            ->tap(fn ($q) => $this->betweenPeriod($q, 'refunded_at', $from, $to));
    }

    private function betweenPeriod($query, string $column, ?\Illuminate\Support\Carbon $from, ?\Illuminate\Support\Carbon $to): void
    {
        if ($from !== null) {
            $query->where($column, '>=', $from);
        }
        if ($to !== null) {
            $query->where($column, '<=', $to);
        }
    }

    private function ticketsByStatus(Event $event): array
    {
        $labels = TicketStatus::query()->get()->keyBy('id');

        return Ticket::query()
            ->where('event_id', $event->id)
            ->selectRaw('status_id, COUNT(*) as total')
            ->groupBy('status_id')
            ->get()
            ->map(fn ($row) => [
                'status' => $labels[$row->status_id]?->slug,
                'label' => $labels[$row->status_id]?->name,
                'count' => (int) $row->total,
            ])->values()->all();
    }

    private function revenue(Event $event): array
    {
        $paidStatusIds = PaymentStatus::idsFor([PaymentStatus::PAID]);

        // Recebido de verdade: pagamentos confirmados (baixa manual com
        // desconto conta o valor da baixa) — FR-005
        $confirmedGross = (string) Payment::query()
            ->whereHas('order', fn ($q) => $q->where('event_id', $event->id))
            ->whereIn('status_id', $paidStatusIds)
            ->sum('amount');

        // Devoluções: total (informativo) e parciais (abatem o confirmado —
        // estorno TOTAL já saiu do bruto porque o payment vira refunded)
        $refundedTotal = (string) Ticket::query()
            ->where('event_id', $event->id)
            ->whereNotNull('refunded_at')
            ->sum('refund_amount');

        $partialRefunds = (string) Ticket::query()
            ->where('event_id', $event->id)
            ->whereNotNull('refunded_at')
            ->whereHas('order.payments', fn ($q) => $q->whereIn('status_id', $paidStatusIds))
            ->sum('refund_amount');

        $confirmed = bcsub($confirmedGross, $partialRefunds, 2);

        $pending = (string) $event->orders()
            ->whereIn('status_id', OrderStatus::idsFor([OrderStatus::PENDING]))
            ->sum('total_amount');

        return [
            'confirmed' => $this->money($confirmed),
            'refunded' => $this->money($refundedTotal),
            'pending' => $this->money($pending),
            'projected' => $this->money(bcadd($confirmed, $pending, 2)),
        ];
    }

    /** Grade por PESSOA: titular e acompanhante, cada um com a sua camisa. */
    private function shirtGrid($tickets, int $totalPeople): array
    {
        $cells = [];
        $bump = function (?string $model, ?string $size) use (&$cells) {
            $key = ($model ?? '—').'|'.($size ?? '—');
            $cells[$key] ??= ['model' => $model, 'size' => $size, 'count' => 0];
            $cells[$key]['count']++;
        };

        foreach ($tickets as $ticket) {
            $bump($ticket->shirtModel?->label, $ticket->shirtSize?->label);

            // Cada assento além do titular é uma pessoa (casal = acompanhante)
            for ($extra = 1; $extra < $this->seats($ticket); $extra++) {
                $bump($ticket->companionShirtModel?->label, $ticket->companionShirtSize?->label);
            }
        }

        return [
            'grid' => collect($cells)
                ->sortBy([['model', 'asc'], ['size', 'asc']])
                ->values()->all(),
            'totalPeople' => $totalPeople,
        ];
    }

    private function byLot(Event $event, $tickets): array
    {
        $byLot = $tickets->groupBy('ticket_lot_id');

        return $event->ticketLots()->orderBy('sort')->get()
            ->map(function ($lot) use ($byLot) {
                $sold = $byLot->get($lot->id, collect());

                return [
                    'lot' => $lot->name,
                    'sold' => $sold->count(),
                    'limit' => $lot->quantity,
                    'revenue' => $this->money(
                        (string) $sold->sum(fn (Ticket $t) => (float) $t->unit_price)
                    ),
                ];
            })->values()->all();
    }

    private function byMethod(Event $event): array
    {
        return Payment::query()
            ->whereHas('order', fn ($q) => $q->where('event_id', $event->id))
            ->whereIn('status_id', PaymentStatus::idsFor([PaymentStatus::PAID]))
            ->selectRaw('method, COUNT(*) as total, SUM(amount) as amount')
            ->groupBy('method')
            ->orderBy('method')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->method,
                'count' => (int) $row->total,
                'amount' => $this->money((string) $row->amount),
            ])->values()->all();
    }

    private function courtesies(Event $event, $tickets): array
    {
        $vouchers = CourtesyVoucher::query()->where('event_id', $event->id);

        return [
            'issued' => (clone $vouchers)->count(),
            'redeemed' => (clone $vouchers)->where('status', CourtesyVoucher::REDEEMED)->count(),
            'courtesyTickets' => $tickets->where('is_courtesy', true)->count(),
            'limits' => [
                'allowCourtesy' => (bool) $event->allow_courtesy,
                'paidThreshold' => $event->courtesy_paid_threshold,
                'limitPerAccount' => $event->courtesy_limit_per_account,
            ],
        ];
    }

    protected function money(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
