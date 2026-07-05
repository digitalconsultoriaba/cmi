<?php

namespace App\Http\Resources\Admin;

use App\Domain\Events\Services\BudgetCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Orçamento completo do evento: cabeçalho + filhos + resumo derivado (spec 011).
 */
class BudgetPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'plan' => [
                'id' => $this->id,
                'eventId' => $this->event_id,
                'expectedPaying' => (int) $this->expected_paying,
                'expectedCourtesy' => (int) $this->expected_courtesy,
                'expectedGuests' => (int) $this->expected_guests,
                'expectedStaff' => (int) $this->expected_staff,
                'expectedSpeakers' => (int) $this->expected_speakers,
                'otherRevenue' => $this->other_revenue,
                'safetyMarginPct' => $this->safety_margin_pct,
                'notes' => $this->notes,
            ],
            'costItems' => BudgetCostItemResource::collection($this->costItems),
            'ticketLots' => BudgetTicketLotResource::collection($this->ticketLots),
            'sponsorships' => BudgetSponsorshipResource::collection($this->sponsorships),
            'scenarios' => BudgetScenarioResource::collection($this->scenarios),
            'summary' => app(BudgetCalculator::class)->summary($this->resource),
        ];
    }
}
