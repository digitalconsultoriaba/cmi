<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetCostItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'category' => $this->category,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unit_price,
            'totalAmount' => $this->total_amount,
            'supplierName' => $this->supplier_name,
            'status' => $this->status,
            'notes' => $this->notes,
            'financialEntryId' => $this->financial_entry_id,
            'convertible' => $this->financial_entry_id === null,
        ];
    }
}
