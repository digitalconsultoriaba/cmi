<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtesyVoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status,
            'ticketTypeId' => $this->ticket_type_id,
            'ticketTypeName' => $this->ticketType?->name,
            'distributedAt' => $this->distributed_at?->toISOString(),
            'redeemedAt' => $this->redeemed_at?->toISOString(),
            'note' => $this->note,
        ];
    }
}
