<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\FinancialEntry;
use App\Domain\Events\Models\FinancialRecurrence;
use Illuminate\Support\Carbon;

/**
 * Recorrências (spec 010): materializa lançamentos futuros até o término/limite,
 * sem loop infinito (limite de segurança por execução).
 */
class FinancialRecurrenceService
{
    private const SAFETY_LIMIT = 24; // no máximo 24 lançamentos por execução

    public function generateDue(?Carbon $until = null): int
    {
        $until ??= Carbon::now(config('events.timezone'))->addMonth();
        $created = 0;

        foreach (FinancialRecurrence::query()->where('is_active', true)->get() as $rec) {
            $created += $this->generateFor($rec, $until);
        }

        return $created;
    }

    public function generateFor(FinancialRecurrence $rec, Carbon $until): int
    {
        $cursor = $rec->last_generated_on
            ? $this->advance($rec, Carbon::parse($rec->last_generated_on))
            : Carbon::parse($rec->starts_on);

        $count = 0;
        $emitted = FinancialEntry::query()->where('recurrence_id', $rec->id)->count();

        while ($cursor->lessThanOrEqualTo($until) && $count < self::SAFETY_LIMIT) {
            if ($rec->ends_on && $cursor->greaterThan(Carbon::parse($rec->ends_on))) {
                break;
            }
            if ($rec->max_occurrences && ($emitted + $count) >= $rec->max_occurrences) {
                break;
            }

            FinancialEntry::query()->create([
                'direction' => $rec->direction, 'description' => $rec->description,
                'amount' => $rec->amount, 'category_id' => $rec->category_id,
                'payment_method_id' => $rec->payment_method_id, 'event_id' => $rec->event_id,
                'person_id' => $rec->person_id, 'due_date' => $cursor->toDateString(),
                'origin' => 'manual', 'recurrence_id' => $rec->id,
            ]);
            $rec->forceFill(['last_generated_on' => $cursor->toDateString()])->save();

            $cursor = $this->advance($rec, $cursor);
            $count++;
        }

        return $count;
    }

    private function advance(FinancialRecurrence $rec, Carbon $date): Carbon
    {
        return match ($rec->frequency) {
            'weekly' => $date->copy()->addWeek(),
            'yearly' => $date->copy()->addYearNoOverflow(),
            default => $date->copy()->addMonthNoOverflow(),
        };
    }
}
