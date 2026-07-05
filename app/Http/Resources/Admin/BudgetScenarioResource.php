<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetScenarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'paying' => (int) $this->paying,
            'avgTicket' => $this->avg_ticket,
            'sponsorship' => $this->sponsorship,
            'cost' => $this->cost,
            'otherRevenue' => $this->other_revenue,
            'closesBudget' => $this->closesBudget(),
        ];
    }
}
