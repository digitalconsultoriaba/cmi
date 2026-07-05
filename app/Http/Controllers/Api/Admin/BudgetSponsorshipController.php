<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\BudgetPlan;
use App\Domain\Events\Models\BudgetSponsorship;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\BudgetConversionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BudgetSponsorshipRequest;
use App\Http\Resources\Admin\BudgetSponsorshipResource;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class BudgetSponsorshipController extends Controller
{
    public function store(BudgetSponsorshipRequest $request, Event $event)
    {
        $plan = BudgetPlan::query()->firstOrCreate(['event_id' => $event->id]);
        $sponsorship = $plan->sponsorships()->create($request->columns());

        return BudgetSponsorshipResource::make($sponsorship)->response()->setStatusCode(201);
    }

    public function update(BudgetSponsorshipRequest $request, Event $event, BudgetSponsorship $sponsorship)
    {
        $this->assertBelongs($sponsorship, $event);
        $sponsorship->update($request->columns());

        return BudgetSponsorshipResource::make($sponsorship->fresh());
    }

    public function destroy(Event $event, BudgetSponsorship $sponsorship)
    {
        $this->assertBelongs($sponsorship, $event);
        $sponsorship->delete();

        return response()->noContent();
    }

    public function generateReceivable(Request $request, Event $event, BudgetSponsorship $sponsorship, BudgetConversionService $conversion)
    {
        $this->assertBelongs($sponsorship, $event);
        $entry = $conversion->toReceivable($sponsorship, $event, $request->user());

        return ApiResponse::data([
            'financialEntryId' => $entry->id,
            'sponsorship' => BudgetSponsorshipResource::make($sponsorship->fresh()),
        ], 201);
    }

    private function assertBelongs(BudgetSponsorship $sponsorship, Event $event): void
    {
        abort_unless($sponsorship->plan->event_id === $event->id, 404);
    }
}
