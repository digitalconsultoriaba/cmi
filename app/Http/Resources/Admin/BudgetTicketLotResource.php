<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetTicketLotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'unitPrice' => $this->unit_price,
            'expectedQuantity' => (int) $this->expected_quantity,
            'expectedPaying' => $this->expected_paying !== null ? (int) $this->expected_paying : (int) $this->expected_quantity,
            'expectedRevenue' => $this->expectedRevenue(),
            'notes' => $this->notes,
        ];
    }
}
