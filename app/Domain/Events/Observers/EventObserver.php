<?php

namespace App\Domain\Events\Observers;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventDay;

/**
 * Garante que todo evento tenha o Dia 1 (spec 012) — criado a partir da data
 * principal ao nascer o evento. Idempotente.
 */
class EventObserver
{
    public function created(Event $event): void
    {
        $this->ensureDayOne($event);
    }

    public function ensureDayOne(Event $event): void
    {
        if ($event->eventDays()->exists()) {
            return;
        }

        EventDay::query()->create([
            'event_id' => $event->id,
            'day_number' => 1,
            'event_date' => $event->starts_at?->toDateString() ?? now()->toDateString(),
            'starts_at' => $event->starts_at?->format('H:i:s'),
            'ends_at' => $event->ends_at?->format('H:i:s'),
        ]);
    }
}
