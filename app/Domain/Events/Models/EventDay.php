<?php

namespace App\Domain\Events\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Dia operacional do evento (spec 012). Situação DERIVADA; colunas só para
 * ações auditadas (finalização/bloqueio/reabertura).
 */
class EventDay extends BaseModel
{
    /** Janela automática: libera N horas antes do início; encerra na hora final. */
    public const AUTO_MARGIN_HOURS = 3;

    protected $casts = [
        'event_date' => 'date',
        'finalized_at' => 'datetime',
        'blocked_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(TicketDayCheckin::class);
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function isFinished(): bool
    {
        return $this->finalized_at !== null;
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /** Início do dia (data + hora início; fallback 00:00) no fuso do evento. */
    public function windowStart(): Carbon
    {
        $tz = config('events.timezone');
        $date = Carbon::parse($this->event_date)->toDateString();
        $time = $this->starts_at ? substr((string) $this->starts_at, 0, 8) : '00:00:00';

        return Carbon::parse($date.' '.$time, $tz);
    }

    /** Fim do dia (data + hora final; fallback 23:59:59) no fuso do evento. */
    public function windowEnd(): Carbon
    {
        $tz = config('events.timezone');
        $date = Carbon::parse($this->event_date)->toDateString();
        $time = $this->ends_at ? substr((string) $this->ends_at, 0, 8) : '23:59:59';

        return Carbon::parse($date.' '.$time, $tz);
    }

    /**
     * Situação derivada (spec 012, FR-019). Em eventos MULTI-DIA a janela é
     * automática: libera AUTO_MARGIN_HOURS antes do início e finaliza
     * AUTO_MARGIN_HOURS após o fim. Reabertura manual desliga o auto-finish.
     */
    public function status(): string
    {
        if ($this->finalized_at !== null) {
            return EventDayStatus::FINISHED;
        }
        if ($this->blocked_at !== null) {
            return EventDayStatus::BLOCKED;
        }

        if ($this->event && $this->event->durationDays() > 1) {
            $now = Carbon::now(config('events.timezone'));
            // Encerra na HORA FINAL cadastrada; libera 3h antes do início.
            if ($this->reopened_at === null && $now->greaterThan($this->windowEnd())) {
                return EventDayStatus::FINISHED;
            }
            if ($now->lessThan($this->windowStart()->subHours(self::AUTO_MARGIN_HOURS))) {
                return EventDayStatus::BLOCKED;
            }
        }

        $has = $this->relationLoaded('checkins')
            ? $this->checkins->isNotEmpty()
            : $this->checkins()->exists();

        return $has ? EventDayStatus::IN_PROGRESS : EventDayStatus::OPEN;
    }

    /** Aceita check-in agora? (aberto ou em andamento) */
    public function isOperable(): bool
    {
        $s = $this->status();

        return $s === EventDayStatus::OPEN || $s === EventDayStatus::IN_PROGRESS;
    }
}
