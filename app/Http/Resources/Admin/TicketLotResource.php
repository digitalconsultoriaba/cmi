<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketLotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'ticketTypeId' => $this->ticket_type_id,
            'ticketTypeName' => $this->ticketType?->name,
            'priceOverride' => $this->price_override,
            'startsAt' => $this->starts_at?->toISOString(),
            'endsAt' => $this->ends_at?->toISOString(),
            'quantity' => $this->quantity,
            'soldCount' => $this->sold_count,
            'isActive' => $this->is_active,
            'sort' => $this->sort,
            // Derivações da fundação
            'isCurrent' => $this->isCurrent(),
            'soldOut' => $this->soldOut(),
            'effectivePrice' => $this->effectivePrice(),
        ];
    }
}
