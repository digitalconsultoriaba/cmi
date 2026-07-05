<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\BudgetPlan;
use App\Domain\Events\Models\BudgetTicketLot;
use App\Domain\Events\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BudgetTicketLotRequest;
use App\Http\Resources\Admin\BudgetTicketLotResource;

class BudgetTicketLotController extends Controller
{
    public function store(BudgetTicketLotRequest $request, Event $event)
    {
        $plan = BudgetPlan::query()->firstOrCreate(['event_id' => $event->id]);
        $lot = $plan->ticketLots()->create($request->columns());

        return BudgetTicketLotResource::make($lot)->response()->setStatusCode(201);
    }

    public function update(BudgetTicketLotRequest $request, Event $event, BudgetTicketLot $lot)
    {
        $this->assertBelongs($lot, $event);
        $lot->update($request->columns());

        return BudgetTicketLotResource::make($lot->fresh());
    }

    public function destroy(Event $event, BudgetTicketLot $lot)
    {
        $this->assertBelongs($lot, $event);
        $lot->delete();

        return response()->noContent();
    }

    private function assertBelongs(BudgetTicketLot $lot, Event $event): void
    {
        abort_unless($lot->plan->event_id === $event->id, 404);
    }
}
