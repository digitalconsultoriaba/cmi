<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventDay;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Gestão dos dias do evento (spec 012): duração/datas (upsert), finalização,
 * reabertura e bloqueio — sempre auditadas. Escritas em DB::transaction.
 */
class EventDayService
{
    /**
     * Define a duração e os dias do evento. `$days` = [['date','startsAt','endsAt','label'], ...]
     * já ordenados/validados pela request. Renumera pela ordem das datas e recusa
     * remover dia que tenha check-ins.
     */
    public function upsertDays(Event $event, array $days, User $actor): void
    {
        // Ordena por data e renumera
        usort($days, fn ($a, $b) => strcmp($a['date'], $b['date']));

        DB::transaction(function () use ($event, $days) {
            $existing = $event->eventDays()->get()->keyBy('day_number');
            $keep = [];

            foreach (array_values($days) as $i => $d) {
                $number = $i + 1;
                $keep[] = $number;
                $attrs = [
                    'event_date' => $d['date'],
                    'starts_at' => $d['startsAt'] ?? null,
                    'ends_at' => $d['endsAt'] ?? null,
                    'label' => $d['label'] ?? null,
                ];

                $day = $existing->get($number);
                if ($day) {
                    $day->update($attrs);
                } else {
                    $event->eventDays()->create(array_merge(['day_number' => $number], $attrs));
                }
            }

            // Dias que sobraram (além da nova duração) são removidos — recusa se têm presença
            foreach ($existing as $number => $day) {
                if (in_array($number, $keep, true)) {
                    continue;
                }
                if ($day->checkins()->exists()) {
                    throw new DomainRuleViolation(
                        "O Dia {$number} já tem check-in e não pode ser removido.",
                        'day_has_checkins'
                    );
                }
                $day->delete();
            }
        });
    }

    public function finalize(EventDay $day, User $actor): EventDay
    {
        if ($day->isFinished()) {
            throw new DomainRuleViolation('Este dia já está finalizado.', 'already_finished');
        }

        $day->forceFill(['finalized_at' => now(), 'finalized_by' => $actor->id])->save();

        activity('event_day.finalized')->performedOn($day)->causedBy($actor)
            ->withProperties(['reference' => 'Dia '.$day->day_number, 'eventId' => $day->event_id])
            ->log('Dia '.$day->day_number.' do evento finalizado');

        return $day->fresh();
    }

    public function reopen(EventDay $day, User $actor, string $reason): EventDay
    {
        if (! $day->isFinished()) {
            throw new DomainRuleViolation('Só é possível reabrir um dia finalizado.', 'not_finished');
        }

        $day->forceFill([
            'finalized_at' => null,
            'finalized_by' => null,
            'reopened_at' => now(),
            'reopened_by' => $actor->id,
            'reopen_reason' => $reason,
        ])->save();

        activity('event_day.reopened')->performedOn($day)->causedBy($actor)
            ->withProperties(['reference' => 'Dia '.$day->day_number, 'eventId' => $day->event_id, 'reason' => $reason])
            ->log('Dia '.$day->day_number.' do evento reaberto: '.$reason);

        return $day->fresh();
    }

    public function setBlocked(EventDay $day, bool $blocked, User $actor): EventDay
    {
        $day->forceFill([
            'blocked_at' => $blocked ? now() : null,
            'blocked_by' => $blocked ? $actor->id : null,
        ])->save();

        return $day->fresh();
    }
}
