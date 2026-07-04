<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Pagamento efetivo mais recente (para exibir forma/data de pagamento).
        $paid = $this->whenLoaded('payments', function () {
            return $this->payments
                ->filter(fn ($p) => $p->status?->slug === 'paid')
                ->sortByDesc('paid_at')
                ->first();
        }, null);

        return [
            'code' => $this->code,
            'status' => $this->status?->slug,
            'totalAmount' => $this->total_amount,
            'reservedUntil' => $this->reserved_until?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'paymentMethod' => $paid?->method,
            'paidAt' => $paid?->paid_at?->toISOString(),
            'event' => [
                'name' => $this->event?->name,
                'slug' => $this->event?->slug,
                'supportWhatsapp' => $this->event?->support_whatsapp,
                'supportEmail' => $this->event?->support_email,
            ],
            'tickets' => TicketResource::collection($this->whenLoaded('tickets')),
        ];
    }
}
