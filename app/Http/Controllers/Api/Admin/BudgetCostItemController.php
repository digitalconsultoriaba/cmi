<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\BudgetCostItem;
use App\Domain\Events\Models\BudgetPlan;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\BudgetConversionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BudgetCostItemRequest;
use App\Http\Resources\Admin\BudgetCostItemResource;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class BudgetCostItemController extends Controller
{
    public function store(BudgetCostItemRequest $request, Event $event)
    {
        $plan = BudgetPlan::query()->firstOrCreate(['event_id' => $event->id]);
        $item = $plan->costItems()->create($request->columns());

        return BudgetCostItemResource::make($item)->response()->setStatusCode(201);
    }

    public function update(BudgetCostItemRequest $request, Event $event, BudgetCostItem $item)
    {
        $this->assertBelongs($item, $event);
        $item->update($request->columns());

        return BudgetCostItemResource::make($item->fresh());
    }

    public function destroy(Event $event, BudgetCostItem $item)
    {
        $this->assertBelongs($item, $event);
        $item->delete(); // soft delete — conta financeira gerada é preservada

        return response()->noContent();
    }

    public function duplicate(Event $event, BudgetCostItem $item)
    {
        $this->assertBelongs($item, $event);
        $copy = $item->plan->costItems()->create([
            'description' => $item->description.' (cópia)',
            'category' => $item->category,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'total_amount' => $item->total_amount,
            'supplier_name' => $item->supplier_name,
            'status' => $item->status,
            'notes' => $item->notes,
            // sem financial_entry_id — a cópia nasce não convertida
        ]);

        return BudgetCostItemResource::make($copy)->response()->setStatusCode(201);
    }

    public function generatePayable(Request $request, Event $event, BudgetCostItem $item, BudgetConversionService $conversion)
    {
        $this->assertBelongs($item, $event);
        $entry = $conversion->toPayable($item, $event, $request->user());

        return ApiResponse::data([
            'financialEntryId' => $entry->id,
            'item' => BudgetCostItemResource::make($item->fresh()),
        ], 201);
    }

    /** Garante que o item pertence ao orçamento deste evento. */
    private function assertBelongs(BudgetCostItem $item, Event $event): void
    {
        abort_unless($item->plan->event_id === $event->id, 404);
    }
}
