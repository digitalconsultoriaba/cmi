<?php

namespace App\Http\Controllers\Api\Finance;

use App\Domain\Events\Models\FinancialRecurrence;
use App\Domain\Events\Services\FinancialRecurrenceService;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class RecurrenceController extends Controller
{
    public function index()
    {
        return ApiResponse::data(FinancialRecurrence::query()->with(['category', 'event'])->latest('id')->get()
            ->map(fn ($r) => $this->present($r))->all());
    }

    public function store(Request $request, FinancialRecurrenceService $service)
    {
        $data = $request->validate([
            'direction' => ['required', 'in:payable,receivable'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category_id' => ['nullable', 'integer'], 'person_id' => ['nullable', 'integer'],
            'event_id' => ['nullable', 'integer'], 'payment_method_id' => ['nullable', 'integer'],
            'frequency' => ['required', 'in:weekly,monthly,yearly'],
            'starts_on' => ['required', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'max_occurrences' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);
        $rec = FinancialRecurrence::query()->create($data);
        $service->generateFor($rec, \Illuminate\Support\Carbon::parse($rec->starts_on));

        return ApiResponse::data($this->present($rec->fresh(['category', 'event'])), 201);
    }

    public function update(Request $request, FinancialRecurrence $recurrence)
    {
        $recurrence->update($request->validate([
            'description' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'is_active' => ['sometimes', 'boolean'], 'ends_on' => ['nullable', 'date'],
        ]));

        return ApiResponse::data($this->present($recurrence->fresh(['category', 'event'])));
    }

    public function destroy(FinancialRecurrence $recurrence)
    {
        $recurrence->delete();

        return ApiResponse::data(null);
    }

    private function present(FinancialRecurrence $r): array
    {
        return [
            'id' => $r->id, 'direction' => $r->direction, 'description' => $r->description,
            'amount' => number_format((float) $r->amount, 2, '.', ''), 'frequency' => $r->frequency,
            'startsOn' => $r->starts_on?->toDateString(), 'endsOn' => $r->ends_on?->toDateString(),
            'category' => $r->category?->name, 'event' => $r->event?->name, 'isActive' => (bool) $r->is_active,
        ];
    }
}
