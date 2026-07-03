<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'capacity' => $this->capacity,
            'seatsPerTicket' => $this->seats_per_ticket,
            'isCouple' => $this->is_couple,
            'includesShirt' => $this->includes_shirt,
            'includesKit' => $this->includes_kit,
            'isCourtesy' => $this->is_courtesy,
            'audience' => $this->audience,
            'isActive' => $this->is_active,
            'sort' => $this->sort,
            // Derivações da fundação
            'available' => $this->available(),
            'soldOut' => $this->soldOut(),
            'soldCount' => $this->soldCount(),
        ];
    }
}
