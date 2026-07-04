<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\FinancialEntry;
use App\Domain\Events\Models\FinancialSettlement;
use Illuminate\Support\Carbon;

/**
 * Leituras derivadas do financeiro (spec 010) — dashboard geral, resultado por
 * evento e relatórios. Tudo calculado na consulta (princípio II).
 */
class FinancialReportService
{
    public const REPORT_TYPES = [
        'geral', 'evento', 'contas-a-pagar', 'contas-a-receber', 'categoria',
        'pessoa', 'forma', 'ingressos', 'patrocinios', 'despesas-evento',
        'previsto-realizado',
    ];

    /** Query base de lançamentos não cancelados, com filtros aplicados. */
    private function scoped(array $filters)
    {
        $q = FinancialEntry::query()->whereNull('cancelled_at');

        if (! empty($filters['event'])) {
            $q->where('event_id', $filters['event']);
        }
        if (! empty($filters['direction'])) {
            $q->where('direction', $filters['direction']);
        }
        if (! empty($filters['category'])) {
            $q->where('category_id', $filters['category']);
        }
        if (! empty($filters['person'])) {
            $q->where('person_id', $filters['person']);
        }
        if (! empty($filters['paymentMethod'])) {
            $q->where('payment_method_id', $filters['paymentMethod']);
        }
        if (! empty($filters['from'])) {
            $q->where('due_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $q->where('due_date', '<=', $filters['to']);
        }

        return $q;
    }

    /** Os 6 números canônicos para um escopo. */
    public function totals(array $filters): array
    {
        $base = $this->scoped($filters);

        $recPrev = (string) (clone $base)->where('direction', FinancialEntry::RECEIVABLE)->sum('amount');
        $recReal = (string) (clone $base)->where('direction', FinancialEntry::RECEIVABLE)->sum('settled_amount');
        $desPrev = (string) (clone $base)->where('direction', FinancialEntry::PAYABLE)->sum('amount');
        $desReal = (string) (clone $base)->where('direction', FinancialEntry::PAYABLE)->sum('settled_amount');

        return [
            'receitaPrevista' => $this->money($recPrev),
            'receitaRealizada' => $this->money($recReal),
            'receitaPendente' => $this->money(bcsub($recPrev, $recReal, 2)),
            'despesaPrevista' => $this->money($desPrev),
            'despesaRealizada' => $this->money($desReal),
            'despesaPendente' => $this->money(bcsub($desPrev, $desReal, 2)),
            'saldoPrevisto' => $this->money(bcsub($recPrev, $desPrev, 2)),
            'saldoRealizado' => $this->money(bcsub($recReal, $desReal, 2)),
            'resultado' => $this->money(bcsub($recReal, $desReal, 2)),
        ];
    }

    /** Resultado do evento (centro de resultado). */
    public function eventResult(Event $event): array
    {
        $t = $this->totals(['event' => $event->id]);
        $t['event'] = ['id' => $event->id, 'name' => $event->name];
        $t['overdue'] = $this->overdue(['event' => $event->id]);

        return $t;
    }

    /** Dashboard geral. */
    public function dashboard(array $filters): array
    {
        $tz = config('events.timezone');
        $monthFrom = Carbon::now($tz)->startOfMonth()->toDateString();
        $monthTo = Carbon::now($tz)->endOfMonth()->toDateString();

        $monthScope = array_merge($filters, ['from' => $monthFrom, 'to' => $monthTo]);
        $mBase = $this->scoped($monthScope);

        // Recebido/pago no mês = baixas (settlements) com settled_on no mês
        $receivedMonth = (string) FinancialSettlement::query()
            ->where('kind', FinancialSettlement::RECEIPT)
            ->whereBetween('settled_on', [$monthFrom, $monthTo])->sum('amount');
        $paidMonth = (string) FinancialSettlement::query()
            ->where('kind', FinancialSettlement::PAYMENT)
            ->whereBetween('settled_on', [$monthFrom, $monthTo])->sum('amount');

        $toReceive = (string) (clone $mBase)->where('direction', FinancialEntry::RECEIVABLE)
            ->sum(\DB::raw('amount - settled_amount'));
        $toPay = (string) (clone $mBase)->where('direction', FinancialEntry::PAYABLE)
            ->sum(\DB::raw('amount - settled_amount'));

        $totals = $this->totals($filters);

        // Resultado por evento
        $byEvent = FinancialEntry::query()->whereNull('cancelled_at')->whereNotNull('event_id')
            ->selectRaw("event_id,
                SUM(CASE WHEN direction='receivable' THEN settled_amount ELSE 0 END) as rec,
                SUM(CASE WHEN direction='payable' THEN settled_amount ELSE 0 END) as des")
            ->groupBy('event_id')->get()
            ->map(fn ($r) => [
                'event' => Event::query()->find($r->event_id)?->name,
                'result' => $this->money(bcsub((string) $r->rec, (string) $r->des, 2)),
            ])->sortByDesc(fn ($e) => (float) $e['result'])->values();

        return [
            'month' => [
                'toReceive' => $this->money($toReceive), 'received' => $this->money($receivedMonth),
                'toPay' => $this->money($toPay), 'paid' => $this->money($paidMonth),
            ],
            'overdue' => [
                'payable' => $this->overdueDir(FinancialEntry::PAYABLE, $filters),
                'receivable' => $this->overdueDir(FinancialEntry::RECEIVABLE, $filters),
            ],
            'balances' => [
                'expected' => $totals['saldoPrevisto'],
                'realized' => $totals['saldoRealizado'],
                'monthResult' => $this->money(bcsub($receivedMonth, $paidMonth, 2)),
            ],
            'bestEvents' => $byEvent->take(5)->all(),
            'worstEvents' => $byEvent->reverse()->filter(fn ($e) => (float) $e['result'] < 0)->take(5)->values()->all(),
            'dueBuckets' => $this->dueBuckets($filters),
            'upcoming' => $this->upcoming($filters),
        ];
    }

    private function overdue(array $filters): array
    {
        return [
            'payable' => $this->overdueDir(FinancialEntry::PAYABLE, $filters),
            'receivable' => $this->overdueDir(FinancialEntry::RECEIVABLE, $filters),
        ];
    }

    private function overdueDir(string $direction, array $filters): array
    {
        $today = Carbon::now(config('events.timezone'))->toDateString();
        $q = $this->scoped(array_merge($filters, ['from' => null, 'to' => null]))
            ->where('direction', $direction)
            ->where('due_date', '<', $today)
            ->whereColumn('settled_amount', '<', 'amount');

        return [
            'count' => (clone $q)->count(),
            'amount' => $this->money((string) (clone $q)->sum(\DB::raw('amount - settled_amount'))),
        ];
    }

    private function dueBuckets(array $filters): array
    {
        $tz = config('events.timezone');
        $today = Carbon::now($tz)->toDateString();
        $in7 = Carbon::now($tz)->addDays(7)->toDateString();
        $over30 = Carbon::now($tz)->subDays(30)->toDateString();

        $open = fn () => $this->scoped(array_merge($filters, ['from' => null, 'to' => null]))
            ->whereColumn('settled_amount', '<', 'amount');

        return [
            'today' => (clone $open())->whereDate('due_date', $today)->count(),
            'next7' => (clone $open())->whereBetween('due_date', [$today, $in7])->count(),
            'over30' => (clone $open())->where('due_date', '<', $over30)->count(),
        ];
    }

    private function upcoming(array $filters): array
    {
        $today = Carbon::now(config('events.timezone'))->toDateString();

        return $this->scoped(array_merge($filters, ['from' => null, 'to' => null]))
            ->whereColumn('settled_amount', '<', 'amount')
            ->where('due_date', '>=', $today)
            ->orderBy('due_date')->limit(10)->get()
            ->map(fn (FinancialEntry $e) => [
                'entryId' => $e->id, 'description' => $e->description,
                'direction' => $e->direction, 'dueDate' => $e->due_date?->toDateString(),
                'amount' => $this->money($e->balance()),
            ])->all();
    }

    /** Prévia de relatório (linhas). */
    public function reportPreview(string $type, array $filters, ?int $limit = 200): array
    {
        [$columns, $rows] = $this->reportRows($type, $filters);

        return [
            'columns' => $columns,
            'rows' => $limit ? array_slice($rows, 0, $limit) : $rows,
            'total' => count($rows),
            'shown' => $limit ? min($limit, count($rows)) : count($rows),
        ];
    }

    /** Fonte única de prévia e export. */
    public function reportRows(string $type, array $filters): array
    {
        // Relatórios de lista consomem os lançamentos filtrados
        if (in_array($type, ['geral', 'contas-a-pagar', 'contas-a-receber', 'evento',
            'ingressos', 'patrocinios', 'despesas-evento'], true)) {
            $q = $this->scoped($filters);
            if ($type === 'contas-a-pagar' || $type === 'despesas-evento') {
                $q->where('direction', FinancialEntry::PAYABLE);
            }
            if ($type === 'contas-a-receber') {
                $q->where('direction', FinancialEntry::RECEIVABLE);
            }
            if ($type === 'ingressos') {
                $q->where('origin', 'ticket');
            }
            if ($type === 'patrocinios') {
                $q->where('origin', 'sponsorship');
            }

            $columns = ['Descrição', 'Tipo', 'Evento', 'Categoria', 'Valor', 'Baixado', 'Vencimento', 'Situação'];
            $rows = $q->with(['event', 'category'])->orderBy('due_date')->get()
                ->map(fn (FinancialEntry $e) => [
                    $e->description,
                    $e->direction === FinancialEntry::RECEIVABLE ? 'A receber' : 'A pagar',
                    $e->event?->name ?? 'Geral',
                    $e->category?->name ?? '—',
                    $this->money((string) $e->amount),
                    $this->money((string) $e->settled_amount),
                    $e->due_date?->format('d/m/Y'),
                    $e->statusLabel(),
                ])->all();

            return [$columns, $rows];
        }

        // Relatórios agrupados
        $group = match ($type) {
            'categoria' => ['category_id', fn ($id) => \App\Domain\Events\Models\FinancialCategory::find($id)?->name],
            'pessoa' => ['person_id', fn ($id) => \App\Domain\Events\Models\FinancialPerson::find($id)?->name],
            'forma' => ['payment_method_id', fn ($id) => \App\Domain\Events\Models\FinancialPaymentMethod::find($id)?->name],
            default => null,
        };

        if ($group !== null) {
            [$col, $label] = $group;
            $rows = $this->scoped($filters)->selectRaw("$col as gid,
                SUM(amount) as prev, SUM(settled_amount) as real")
                ->groupBy($col)->get()
                ->map(fn ($r) => [
                    $label($r->gid) ?? '—',
                    $this->money((string) $r->prev),
                    $this->money((string) $r->real),
                ])->all();

            return [['Grupo', 'Previsto', 'Realizado'], $rows];
        }

        // previsto-realizado (resumo)
        $t = $this->totals($filters);

        return [
            ['Indicador', 'Valor'],
            [
                ['Receita prevista', $t['receitaPrevista']],
                ['Receita realizada', $t['receitaRealizada']],
                ['Despesa prevista', $t['despesaPrevista']],
                ['Despesa realizada', $t['despesaRealizada']],
                ['Saldo previsto', $t['saldoPrevisto']],
                ['Saldo realizado', $t['saldoRealizado']],
            ],
        ];
    }

    private function money(string $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }
}
