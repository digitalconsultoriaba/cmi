<?php

namespace App\Http\Resources\Admin;

use App\Domain\Events\Models\BudgetSponsorshipStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetSponsorshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $convertible = $this->financial_entry_id === null
            && ! in_array($this->status, BudgetSponsorshipStatus::EXCLUDED, true);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'unitValue' => $this->unit_value,
            'quantity' => (int) $this->quantity,
            'status' => $this->status,
            'expectedRevenue' => $this->expectedRevenue(),
            'notes' => $this->notes,
            'financialEntryId' => $this->financial_entry_id,
            'convertible' => $convertible,
        ];
    }
}
