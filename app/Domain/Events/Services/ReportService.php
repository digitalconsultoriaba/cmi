<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\CourtesyVoucher;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventStatus;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\Payment;
use App\Domain\Events\Models\PaymentStatus;
use App\Domain\Events\Models\SponsorshipInstallment;
use App\Domain\Events\Models\SupportCase;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketStatus;
use Illuminate\Support\Carbon;

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

    // ─────────────────────────────────────────────────────────────────────
    // Spec 009 — painel v2 (derivações de leitura, escopadas por evento)
    // ─────────────────────────────────────────────────────────────────────

    /** Painel do MÓDULO — consolidado de todos os eventos (ou 1, se filtrado). */
    public function overview(?int $eventId, ?Carbon $from, ?Carbon $to): array
    {
        $events = Event::query()
            ->when($eventId, fn ($q) => $q->whereKey($eventId))
            ->get();
        $eventIds = $events->pluck('id');

        $tickets = Ticket::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('status_id', TicketStatus::idsFor(self::ELIGIBLE))
            ->with('ticketType')
            ->get();

        $inPeriod = $tickets->filter(fn (Ticket $t) => $this->within($t->created_at, $from, $to));

        $confirmed = '0.00';
        $pending = '0.00';
        foreach ($events as $event) {
            $rev = $this->revenue($event);
            $confirmed = bcadd($confirmed, $rev['confirmed'], 2);
            $pending = bcadd($pending, $rev['pending'], 2);
        }

        $sponsorshipPaid = (string) SponsorshipInstallment::query()
            ->whereHas('sponsorship', fn ($q) => $q
                ->whereIn('event_id', $eventIds)->where('status', '!=', 'cancelled'))
            ->where('status', 'paid')->sum('paid_amount');

        $refundsOpen = SupportCase::query()
            ->whereIn('event_id', $eventIds)
            ->where('type', 'refund')
            ->whereIn('status', ['open', 'reopened'])
            ->count();

        $statusLabels = EventStatus::query()->get()->keyBy('id');

        return [
            'cards' => [
                'events' => $events->count(),
                'published' => $events->filter(fn ($e) => $e->status?->slug === EventStatus::PUBLISHED)->count(),
                'upcoming' => $events->filter(fn ($e) => $e->starts_at !== null
                    && $e->starts_at->isFuture()
                    && ! in_array($e->status?->slug, EventStatus::TERMINAL, true))->count(),
                'activeRegistrations' => (int) $inPeriod->sum(fn (Ticket $t) => $this->seats($t)),
                'revenueConfirmed' => $this->money($confirmed),
                'revenueProjected' => $this->money(bcadd($confirmed, $pending, 2)),
                'sponsorshipPaid' => $this->money($sponsorshipPaid),
                'refundsOpen' => $refundsOpen,
            ],
            'eventsByStatus' => $events->groupBy('status_id')
                ->map(fn ($group, $statusId) => [
                    'status' => $statusLabels[$statusId]?->slug,
                    'label' => $statusLabels[$statusId]?->name,
                    'count' => $group->count(),
                ])->values()->all(),
            'inscriptionsByMonth' => $this->monthlySeries($inPeriod, $from, $to),
        ];
    }

    /** Painel do EVENTO — contadores da operação + financeiro + gráficos. */
    public function eventPanel(Event $event): array
    {
        $tickets = $this->eligibleTickets($event);
        // Contagem por INGRESSO (linha), para casar com a lista de Inscritos —
        // um casal é 1 inscrição (não 2 assentos). Assentos seguem só na
        // capacidade/portaria.
        $people = fn ($collection) => (int) $collection->count();

        $usedSlug = TicketStatus::USED;
        $rev = $this->revenue($event);

        $byStatus = Ticket::query()
            ->where('event_id', $event->id)
            ->selectRaw('status_id, COUNT(*) as total')
            ->groupBy('status_id')->get()
            ->mapWithKeys(fn ($r) => [
                TicketStatus::query()->find($r->status_id)?->slug => (int) $r->total,
            ]);

        $sponsorshipPaid = (string) SponsorshipInstallment::query()
            ->whereHas('sponsorship', fn ($q) => $q
                ->where('event_id', $event->id)->where('status', '!=', 'cancelled'))
            ->where('status', 'paid')->sum('paid_amount');

        return [
            'counters' => [
                'capacity' => $event->total_capacity,
                'registeredTotal' => $people($tickets),
                'paidConfirmed' => $people($tickets->filter(fn (Ticket $t) => ! $t->is_courtesy
                    && in_array($t->status?->slug, [TicketStatus::PAID, TicketStatus::CONFIRMED, $usedSlug], true))),
                'courtesies' => $tickets->where('is_courtesy', true)->count(),
                'present' => $people($tickets->filter(fn (Ticket $t) => $t->status?->slug === $usedSlug)),
                'awaitingPayment' => ($byStatus[TicketStatus::RESERVED] ?? 0)
                    + ($byStatus[TicketStatus::AWAITING_PAYMENT] ?? 0),
                'cancelled' => $byStatus[TicketStatus::CANCELLED] ?? 0,
                'refunded' => $byStatus[TicketStatus::REFUNDED] ?? 0,
            ],
            'financial' => [
                'expected' => $rev['projected'],
                'confirmed' => $rev['confirmed'],
                'receivable' => $rev['pending'],
                'sponsorshipPaid' => $this->money($sponsorshipPaid),
            ],
            'ticketsByStatus' => $this->ticketsByStatus($event),
            'byTicketType' => $this->byTicketType($event, $tickets),
            'inscriptionsByMonth' => $this->monthlySeries($tickets, null, null),
        ];
    }

    /** Recorte por TIPO de ingresso (substitui o "por loja" do protótipo). */
    public function byTicketType(Event $event, $tickets = null): array
    {
        $tickets ??= $this->eligibleTickets($event);

        return $tickets->groupBy(fn (Ticket $t) => $t->ticketType?->name ?? '—')
            ->map(fn ($group, $name) => [
                'type' => $name,
                'count' => $group->count(), // por INGRESSO (linha), igual à lista de Inscritos
                'revenue' => $this->money((string) $group->sum(fn (Ticket $t) => (float) $t->unit_price)),
            ])->values()->all();
    }

    /** Série mensal de inscrições (pessoas) por mês da compra, no fuso do evento. */
    public function monthlySeries($tickets, ?Carbon $from, ?Carbon $to): array
    {
        $tz = config('events.timezone');

        $counts = [];
        foreach ($tickets as $ticket) {
            $month = $ticket->created_at?->copy()->setTimezone($tz)->format('Y-m');
            if ($month === null) {
                continue;
            }
            $counts[$month] = ($counts[$month] ?? 0) + $this->seats($ticket);
        }

        // Janela contígua: do início ao fim (ou últimos 12 meses até hoje)
        $end = $to?->copy()->setTimezone($tz) ?? Carbon::now($tz);
        $start = $from?->copy()->setTimezone($tz)
            ?? ($counts === [] ? $end->copy()->subMonths(11) : Carbon::createFromFormat('Y-m', min(array_keys($counts)), $tz));
        if ($start->greaterThan($end->copy()->subMonths(11))) {
            $start = $end->copy()->subMonths(11);
        }

        $series = [];
        $cursor = $start->copy()->startOfMonth();
        $last = $end->copy()->startOfMonth();
        while ($cursor->lessThanOrEqualTo($last)) {
            $key = $cursor->format('Y-m');
            $series[] = ['month' => $key, 'count' => $counts[$key] ?? 0];
            $cursor->addMonthNoOverflow();
        }

        return $series;
    }

    /** Lista de inscritos do evento (todas as situações) — sem coluna Loja. */
    public function attendeesList(Event $event, array $filters): array
    {
        $query = Ticket::query()
            ->where('event_id', $event->id)
            ->with(['ticketType', 'status', 'shirtModel', 'shirtSize',
                'companionShirtModel', 'companionShirtSize', 'order.status', 'order.payments.status'])
            ->orderBy('participant_name');

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(fn ($q) => $q
                ->where('participant_name', 'like', "%{$s}%")
                ->orWhere('companion_name', 'like', "%{$s}%")
                ->orWhere('code', 'like', "%{$s}%"));
        }
        if (! empty($filters['ticketType'])) {
            $query->where('ticket_type_id', $filters['ticketType']);
        }
        if (! empty($filters['status'])) {
            $query->whereHas('status', fn ($q) => $q->where('slug', $filters['status']));
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        // Total antes de paginar (contagem de cadastros)
        $total = (clone $query)->count();

        // Paginação opcional (a prévia de relatório não pagina)
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['perPage'] ?? 0);
        if ($perPage > 0) {
            $query->forPage($page, $perPage);
        }

        $items = $query->get()->map(fn (Ticket $t) => [
            'code' => $t->code,
            'participantName' => $t->participant_name,
            'companionName' => $t->companion_name,
            'isCouple' => (bool) $t->ticketType?->is_couple,
            'isCourtesy' => (bool) $t->is_courtesy,
            'ticketTypeName' => $t->ticketType?->name,
            'shirt' => $this->shirtLabel($t->shirtSize?->label, $t->shirtModel?->label),
            'companionShirt' => $this->shirtLabel($t->companionShirtSize?->label, $t->companionShirtModel?->label),
            'amount' => $this->money((string) $t->unit_price),
            'status' => $t->status?->slug,
            'statusLabel' => $t->status?->name,
            'paidAt' => $t->order?->payments
                ?->firstWhere('status.slug', PaymentStatus::PAID)?->paid_at?->toISOString(),
            'purchasedAt' => $t->created_at?->toISOString(),
            'orderCode' => $t->order?->code,
            'orderStatus' => $t->order?->status?->slug,
            'buyerUserId' => $t->order?->buyer_user_id,
            // Pedido ainda precisa de baixa? (pago/parcial → não; cortesia → não)
            'paymentPending' => ! $t->is_courtesy && in_array(
                $t->order?->status?->slug,
                [OrderStatus::PENDING, OrderStatus::PARTIALLY_PAID],
                true
            ),
            'printable' => in_array($t->status?->slug, [
                TicketStatus::PAID, TicketStatus::CONFIRMED, TicketStatus::COURTESY, TicketStatus::USED,
            ], true),
        ])->values();

        return [
            'items' => $items->all(),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage > 0 ? $perPage : $total,
            'lastPage' => $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1,
        ];
    }

    /** Pedidos do evento com situação de pagamento — Financeiro (baixa). */
    public function ordersList(Event $event, ?string $status): array
    {
        $query = $event->orders()
            ->with(['status', 'buyerUser', 'payments.status', 'payments.registeredBy'])
            ->orderByDesc('id');

        if (! empty($status)) {
            $query->whereHas('status', fn ($q) => $q->where('slug', $status));
        }

        $items = $query->get()->map(function ($order) {
            $paid = $order->payments->firstWhere('status.slug', PaymentStatus::PAID);

            return [
                'code' => $order->code,
                'buyerUserId' => $order->buyer_user_id,
                'buyerName' => $order->buyer_name,
                'buyerEmail' => $order->buyer_email,
                'total' => $this->money((string) $order->total_amount),
                'status' => $order->status?->slug,
                'statusLabel' => $order->status?->name,
                'method' => $paid?->method,
                'paidAt' => $paid?->paid_at?->toISOString(),
                // Recebido por: operador da baixa manual OU "Sistema" (automático).
                'receivedBy' => $paid ? ($paid->registeredBy?->name ?? 'Sistema') : null,
                'ticketCount' => $order->tickets()->count(),
                'canSettle' => in_array($order->status?->slug,
                    [OrderStatus::PENDING, OrderStatus::PARTIALLY_PAID], true),
            ];
        });

        return ['items' => $items->all(), 'total' => $items->count()];
    }

    /** Contadores e lista de presença ESCOPADOS ao evento (mesma régua da 007). */
    public function attendancePayload(Event $event, ?string $search): array
    {
        $usedId = TicketStatus::idFor(TicketStatus::USED);

        $query = Ticket::query()
            ->where('event_id', $event->id)
            ->whereIn('status_id', TicketStatus::idsFor(self::ELIGIBLE))
            ->with(['ticketType', 'status'])
            ->orderBy('participant_name');

        if ($search = trim((string) $search)) {
            $query->where(fn ($q) => $q
                ->where('participant_name', 'like', "%{$search}%")
                ->orWhere('companion_name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"));
        }

        $tickets = $query->get();
        $people = fn ($c) => (int) $c->sum(fn (Ticket $t) => $this->seats($t));

        $purchased = $people($tickets);
        $present = $people($tickets->where('status_id', $usedId));

        $validators = \App\Models\User::query()
            ->whereIn('id', $tickets->pluck('validated_by')->filter()->unique())
            ->pluck('name', 'id');

        return [
            'counters' => [
                'purchased' => $purchased,
                'present' => $present,
                'absent' => $purchased - $present,
                'presentPct' => $purchased > 0 ? (int) round($present / $purchased * 100) : 0,
            ],
            'presence' => ['present' => $present, 'absent' => $purchased - $present],
            'items' => $tickets->map(fn (Ticket $t) => [
                'code' => $t->code,
                'participantName' => $t->participant_name,
                'companionName' => $t->companion_name,
                'seats' => $this->seats($t),
                'present' => $t->status?->slug === TicketStatus::USED,
                'usedAt' => $t->used_at?->toISOString(),
                'validatedBy' => $t->validated_by ? ($validators[$t->validated_by] ?? null) : null,
            ])->values()->all(),
        ];
    }

    /** Tipos de relatório para prévia/export. */
    public const REPORT_TYPES = ['inscritos', 'financeiro', 'presencas', 'camisas'];

    /**
     * Prévia de relatório: colunas + linhas + total. As MESMAS linhas
     * alimentam o export .xlsx (invariante 5). `previewLimit` limita a
     * exibição sem truncar o export (que passa limit=null).
     */
    public function reportPreview(Event $event, string $type, array $filters, int $page = 1, int $perPage = 25): array
    {
        [$columns, $rows] = $this->reportRows($event, $type, $filters);
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $shown = array_slice($rows, ($page - 1) * $perPage, $perPage);

        return [
            'columns' => $columns,
            'rows' => array_values($shown),
            'total' => $total,
            'shown' => count($shown),
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => $lastPage,
        ];
    }

    /** Linhas cruas de cada relatório (fonte única de prévia e planilha). */
    public function reportRows(Event $event, string $type, array $filters): array
    {
        return match ($type) {
            'inscritos' => $this->rowsInscritos($event, $filters),
            'financeiro' => $this->rowsFinanceiro($event, $filters),
            'presencas' => $this->rowsPresencas($event),
            'camisas' => $this->rowsCamisas($event),
            default => [[], []],
        };
    }

    private function rowsInscritos(Event $event, array $filters): array
    {
        $columns = ['Participante', 'Tipo', 'Situação', 'Cortesia', 'Tam.', 'Valor'];
        $rows = collect($this->attendeesList($event, $filters)['items'])
            ->map(fn ($i) => [
                $i['participantName'], $i['ticketTypeName'], $i['statusLabel'],
                $i['isCourtesy'] ? 'Sim' : 'Não', $i['shirt'] ?: '—', $i['amount'],
            ])->all();

        return [$columns, $rows];
    }

    private function rowsFinanceiro(Event $event, array $filters): array
    {
        $columns = ['Pedido', 'Comprador', 'Forma', 'Valor', 'Baixa em'];
        $payments = $this->paymentsInPeriod($event, $filters['from'] ?? null, $filters['to'] ?? null)
            ->with(['order'])->orderBy('paid_at')->get();
        $rows = $payments->map(fn ($p) => [
            $p->order?->code, $p->order?->buyer_name,
            self::METHOD_LABELS[$p->method] ?? $p->method,
            $this->money((string) $p->amount),
            $p->paid_at?->setTimezone(config('events.timezone'))->format('d/m/Y H:i'),
        ])->all();

        return [$columns, $rows];
    }

    private function rowsPresencas(Event $event): array
    {
        $columns = ['Ingresso', 'Participante', 'Pessoas', 'Presença', 'Entrada'];
        $rows = collect($this->attendancePayload($event, null)['items'])
            ->map(fn ($i) => [
                $i['code'], $i['participantName'], $i['seats'],
                $i['present'] ? 'Presente' : 'Ausente',
                $i['usedAt'] ? Carbon::parse($i['usedAt'])->setTimezone(config('events.timezone'))->format('d/m/Y H:i') : '—',
            ])->all();

        return [$columns, $rows];
    }

    private function rowsCamisas(Event $event): array
    {
        $columns = ['Modelo', 'Tamanho', 'Estoque', 'Vendidas', 'Disponível'];
        $rows = [];
        foreach ($event->shirtModels()->with('sizes')->orderBy('sort')->get() as $model) {
            foreach ($model->sizes as $size) {
                $available = $size->stock_quantity === null
                    ? 'ilimitado'
                    : (string) ($size->stock_quantity - $size->sold_count);
                $rows[] = [$model->label, $size->label,
                    $size->stock_quantity ?? 'ilimitado', $size->sold_count, $available];
            }
        }

        return [$columns, $rows];
    }

    private function shirtLabel(?string $size, ?string $model): ?string
    {
        if ($size === null && $model === null) {
            return null;
        }

        return trim(($size ?? '').($model ? '/'.$model : ''));
    }

    private function within(?Carbon $dt, ?Carbon $from, ?Carbon $to): bool
    {
        if ($dt === null) {
            return false;
        }
        if ($from !== null && $dt->lessThan($from)) {
            return false;
        }
        if ($to !== null && $dt->greaterThan($to)) {
            return false;
        }

        return true;
    }
}
