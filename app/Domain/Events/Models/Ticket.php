<?php

namespace App\Domain\Events\Models;

use App\Domain\Events\Models\Concerns\TransitionsStatus;
use App\Domain\Events\Support\HasPublicCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends BaseModel
{
    use HasPublicCode;
    use TransitionsStatus;

    public const CODE_PREFIX = 'TCK';
    public const STATUS_LOOKUP = TicketStatus::class;

    protected $casts = [
        'unit_price' => 'decimal:2', // snapshot do momento da compra
        'refund_amount' => 'decimal:2',
        'is_guest' => 'boolean',
        'is_courtesy' => 'boolean',
        'used_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function ticketLot(): BelongsTo
    {
        return $this->belongsTo(TicketLot::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'status_id');
    }

    public function participantUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_user_id');
    }

    public function shirtModel(): BelongsTo
    {
        return $this->belongsTo(EventShirtModel::class, 'shirt_model_id');
    }

    public function shirtSize(): BelongsTo
    {
        return $this->belongsTo(EventShirtSize::class, 'shirt_size_id');
    }

    public function companionShirtModel(): BelongsTo
    {
        return $this->belongsTo(EventShirtModel::class, 'companion_shirt_model_id');
    }

    public function companionShirtSize(): BelongsTo
    {
        return $this->belongsTo(EventShirtSize::class, 'companion_shirt_size_id');
    }

    public function transferredFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'transferred_from_ticket_id');
    }

    public function transferredTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'transferred_to_ticket_id');
    }

    // ── Derivações ──────────────────────────────────────────────────

    /** Vivo = aguarda ou vale participação (não terminal, não expirado). */
    public function isActive(): bool
    {
        return in_array($this->status?->slug, TicketStatus::LIVE, true);
    }
}
