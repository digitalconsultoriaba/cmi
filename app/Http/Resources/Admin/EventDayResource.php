<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventDayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dayNumber' => (int) $this->day_number,
            'date' => $this->event_date?->toDateString(),
            'startsAt' => $this->starts_at ? substr((string) $this->starts_at, 0, 5) : null,
            'endsAt' => $this->ends_at ? substr((string) $this->ends_at, 0, 5) : null,
            'label' => $this->label,
            'status' => $this->status(),
            'checkinCount' => $this->checkins_count
                ?? ($this->relationLoaded('checkins') ? $this->checkins->count() : $this->checkins()->count()),
            'finalizedAt' => $this->finalized_at?->toISOString(),
            'finalizedBy' => $this->finalizedBy?->name,
            'reopenedAt' => $this->reopened_at?->toISOString(),
            'reopenReason' => $this->reopen_reason,
        ];
    }
}
