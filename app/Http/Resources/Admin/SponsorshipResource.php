<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SponsorshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'companyName' => $this->company_name,
            'contact' => $this->contact,
            'totalAmount' => $this->total_amount,
            'paymentMethod' => $this->payment_method,
            'installmentsCount' => $this->installments_count,
            'status' => $this->status,
            'notes' => $this->notes,
            'installments' => $this->installments->map(fn ($installment) => [
                'id' => $installment->id,
                'number' => $installment->number,
                'amount' => $installment->amount,
                'dueDate' => $installment->due_date?->toISOString(),
                'status' => $installment->status,
                'paidAt' => $installment->paid_at?->toISOString(),
                'paidAmount' => $installment->paid_amount,
                'method' => $installment->method,
            ])->values(),
        ];
    }
}
