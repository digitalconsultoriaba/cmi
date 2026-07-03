<?php

namespace App\Http\Resources;

use App\Domain\Events\Models\TicketStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'status' => $this->status?->slug,
            'participantName' => $this->participant_name,
            'participantEmail' => $this->participant_email,
            'ticketTypeName' => $this->ticketType?->name,
            'isCourtesy' => (bool) $this->is_courtesy,
            'unitPrice' => $this->unit_price,
            'shirt' => $this->shirt_size_id ? [
                'model' => $this->shirtModel?->label,
                'size' => $this->shirtSize?->label,
            ] : null,
            'companion' => $this->companion_name ? [
                'name' => $this->companion_name,
                'shirtModel' => $this->companionShirtModel?->label,
                'shirtSize' => $this->companionShirtSize?->label,
            ] : null,
            'event' => [
                'name' => $this->event?->name,
                'slug' => $this->event?->slug,
                'startsAt' => $this->event?->starts_at?->toISOString(),
            ],
            'orderCode' => $this->order?->code,
            'receiptAvailable' => in_array(
                $this->status?->slug,
                [TicketStatus::PAID, TicketStatus::CONFIRMED, TicketStatus::COURTESY, TicketStatus::USED],
                true
            ),
        ];
    }
}
