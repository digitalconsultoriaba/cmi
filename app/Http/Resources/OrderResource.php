<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'status' => $this->status?->slug,
            'totalAmount' => $this->total_amount,
            'reservedUntil' => $this->reserved_until?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'event' => [
                'name' => $this->event?->name,
                'slug' => $this->event?->slug,
            ],
            'tickets' => TicketResource::collection($this->whenLoaded('tickets')),
        ];
    }
}
