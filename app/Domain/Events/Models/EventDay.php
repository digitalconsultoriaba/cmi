<?php

namespace App\Domain\Events\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dia operacional do evento (spec 012). Situação DERIVADA; colunas só para
 * ações auditadas (finalização/bloqueio/reabertura).
 */
class EventDay extends BaseModel
{
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

    /** Situação derivada (spec 012, FR-019). */
    public function status(): string
    {
        if ($this->finalized_at !== null) {
            return EventDayStatus::FINISHED;
        }
        if ($this->blocked_at !== null) {
            return EventDayStatus::BLOCKED;
        }
        $has = $this->relationLoaded('checkins')
            ? $this->checkins->isNotEmpty()
            : $this->checkins()->exists();

        return $has ? EventDayStatus::IN_PROGRESS : EventDayStatus::OPEN;
    }
}
