<?php

namespace App\Http\Controllers\Api\Finance;

use App\Domain\Events\Models\FinancialEntry;
use App\Domain\Events\Services\FinancialEntryService;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EntryController extends Controller
{
    public function __construct(private readonly FinancialEntryService $service)
    {
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'direction' => ['nullable', 'in:payable,receivable'],
            'status' => ['nullable', 'string'],
            'event' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer'],
            'person' => ['nullable', 'integer'],
            'paymentMethod' => ['nullable', 'integer'],
            'origin' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:120'],
            'includeCancelled' => ['nullable', 'boolean'],
            'perPage' => ['nullable', 'in:25,50,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $q = FinancialEntry::query()->with(['category', 'person', 'event', 'paymentMethod', 'settlements']);

        if (empty($data['includeCancelled'])) {
            $q->whereNull('cancelled_at');
        }
        foreach (['direction' => 'direction', 'event' => 'event_id', 'category' => 'category_id',
            'person' => 'person_id', 'paymentMethod' => 'payment_method_id', 'origin' => 'origin'] as $key => $col) {
            if (! empty($data[$key])) {
                $q->where($col, $data[$key]);
            }
        }
        // Filtro de mês em regime de caixa: entra quem VENCE no período (contas
        // ainda em aberto) OU quem foi RECEBIDO/PAGO no período (baixa no mês).
        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;
        if ($from || $to) {
            $q->where(function ($w) use ($from, $to) {
                $w->where(function ($d) use ($from, $to) {
                    if ($from) {
                        $d->where('due_date', '>=', $from);
                    }
                    if ($to) {
                        $d->where('due_date', '<=', $to);
                    }
                })->orWhereHas('settlements', function ($s) use ($from, $to) {
                    $s->where('kind', '!=', 'reversal');
                    if ($from) {
                        $s->whereDate('settled_on', '>=', $from);
                    }
                    if ($to) {
                        $s->whereDate('settled_on', '<=', $to);
                    }
                });
            });
        }
        if (! empty($data['search'])) {
            $q->where('description', 'like', '%'.$data['search'].'%');
        }

        $q->orderBy('due_date')->orderByDesc('id');

        // filtro por situação derivada (pós-consulta, pois é calculada)
        $perPage = (int) ($data['perPage'] ?? 25);
        $page = (int) ($data['page'] ?? 1);
        $all = $q->get();
        if (! empty($data['status'])) {
            $all = $all->filter(fn (FinancialEntry $e) => $e->status() === $data['status'])->values();
        }

        // Data de competência de caixa: recebimento (se baixado) senão vencimento.
        $settledOn = fn (FinancialEntry $e) => $e->settlements
            ->where('kind', '!=', 'reversal')->max('settled_on');
        $refDate = fn (FinancialEntry $e) => optional($settledOn($e) ?? $e->due_date)->toDateString();

        // Mantém no mês só quem vence OU foi recebido no período (regime de caixa):
        // o orWhereHas do SQL traz candidatos; aqui descartamos os baixados fora
        // do período cujo vencimento caía dentro dele.
        if ($from || $to) {
            $all = $all->filter(function ($e) use ($refDate, $from, $to) {
                $ref = $refDate($e);
                return (! $from || $ref >= $from) && (! $to || $ref <= $to);
            })->values();
        }

        // Ordena por recebimento/pagamento: baixados no topo (baixa mais recente
        // primeiro); em aberto logo abaixo, por vencimento mais próximo.
        $all = $all->filter(fn ($e) => $settledOn($e) !== null)->sortByDesc($settledOn)
            ->concat($all->filter(fn ($e) => $settledOn($e) === null)->sortBy('due_date'))
            ->values();
        $total = $all->count();
        $items = $all->slice(($page - 1) * $perPage, $perPage)->values();

        return ApiResponse::data([
            'items' => $items->map(fn (FinancialEntry $e) => $this->present($e))->all(),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => max(1, (int) ceil($total / $perPage)),
            'totals' => [
                'amount' => number_format((float) $all->sum(fn ($e) => (float) $e->amount), 2, '.', ''),
                'settled' => number_format((float) $all->sum(fn ($e) => (float) $e->settled_amount), 2, '.', ''),
                // Dinheiro que entrou/saiu no período (baixas com data no mês).
                'receivedInPeriod' => number_format((float) $all->sum(fn ($e) => (float) $e->settlements
                    ->where('kind', '!=', 'reversal')
                    ->filter(fn ($s) => (! $from || $s->settled_on?->toDateString() >= $from)
                        && (! $to || $s->settled_on?->toDateString() <= $to))
                    ->sum('amount')), 2, '.', ''),
                // Saldo em aberto (ainda a receber/pagar) das contas exibidas.
                'pending' => number_format((float) $all
                    ->filter(fn ($e) => ! in_array($e->status(), [FinancialEntry::SETTLED, FinancialEntry::CANCELLED], true))
                    ->sum(fn ($e) => (float) $e->balance()), 2, '.', ''),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateEntry($request);

        $entry = $this->service->create($data, $request->user());

        return ApiResponse::data($this->present($entry->fresh(['category', 'person', 'event', 'paymentMethod'])), 201);
    }

    public function show(FinancialEntry $entry)
    {
        return ApiResponse::data($this->present(
            $entry->load(['category', 'person', 'event', 'paymentMethod', 'settlements.paymentMethod', 'attachments']),
            detail: true
        ));
    }

    public function update(Request $request, FinancialEntry $entry)
    {
        $data = $request->validate([
            'description' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric'],
            'category_id' => ['nullable', 'integer'],
            'payment_method_id' => ['nullable', 'integer'],
            'event_id' => ['nullable', 'integer'],
            'person_id' => ['nullable', 'integer'],
            'due_date' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string'],
            'justification' => ['nullable', 'string', 'max:500'],
        ]);
        $justification = $data['justification'] ?? null;
        unset($data['justification']);

        $entry = $this->service->update($entry, $data, $justification, $request->user());

        return ApiResponse::data($this->present($entry->load(['category', 'person', 'event', 'paymentMethod'])));
    }

    public function cancel(Request $request, FinancialEntry $entry)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $entry = $this->service->cancel($entry, $data['reason'], $request->user());

        return ApiResponse::data($this->present($entry));
    }

    public function duplicate(Request $request, FinancialEntry $entry)
    {
        $copy = $this->service->duplicate($entry, $request->user());

        return ApiResponse::data($this->present($copy), 201);
    }

    private function validateEntry(Request $request): array
    {
        return $request->validate([
            'direction' => ['required', 'in:payable,receivable'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category_id' => ['nullable', 'integer', 'exists:financial_categories,id'],
            'payment_method_id' => ['nullable', 'integer', 'exists:financial_payment_methods,id'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'person_id' => ['nullable', 'integer', 'exists:financial_people,id'],
            'due_date' => ['required', 'date'],
            'origin' => ['nullable', Rule::in(FinancialEntry::ORIGINS)],
            'notes' => ['nullable', 'string'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:36'],
            'first_due_date' => ['nullable', 'date'],
            'due_dates' => ['nullable', 'array'],
            'due_dates.*' => ['nullable', 'date'],
        ]);
    }

    private function present(FinancialEntry $e, bool $detail = false): array
    {
        $base = [
            'id' => $e->id,
            'direction' => $e->direction,
            'description' => $e->description,
            'amount' => number_format((float) $e->amount, 2, '.', ''),
            'settledAmount' => number_format((float) $e->settled_amount, 2, '.', ''),
            'balance' => $e->balance(),
            'status' => $e->status(),
            'statusLabel' => $e->statusLabel(),
            'dueDate' => $e->due_date?->toDateString(),
            // Data da baixa (recebimento/pagamento): última baixa não-estorno.
            'settledOn' => $e->relationLoaded('settlements')
                ? optional($e->settlements->where('kind', '!=', 'reversal')->max('settled_on'))->toDateString()
                : null,
            'origin' => $e->origin,
            'category' => $e->category?->name,
            'categoryId' => $e->category_id,
            'person' => $e->person?->name,
            'personId' => $e->person_id,
            'event' => $e->event ? ['id' => $e->event->id, 'name' => $e->event->name] : null,
            'paymentMethod' => $e->paymentMethod?->name,
            'paymentMethodId' => $e->payment_method_id,
            'readonly' => $e->isMirror(),
            'installment' => $e->installment_number
                ? ['number' => $e->installment_number, 'total' => $e->installment_total] : null,
            'notes' => $e->notes,
        ];

        if ($detail) {
            $base['settlements'] = $e->settlements->map(fn ($s) => [
                'id' => $s->id, 'amount' => number_format((float) $s->amount, 2, '.', ''),
                'kind' => $s->kind, 'settledOn' => $s->settled_on?->toDateString(),
                'method' => $s->paymentMethod?->name, 'note' => $s->note,
            ])->values();
            $base['attachments'] = $e->attachments->map(fn ($a) => [
                'id' => $a->id, 'kind' => $a->kind, 'name' => $a->original_name,
            ])->values();
        }

        return $base;
    }
}
