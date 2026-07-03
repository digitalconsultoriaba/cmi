<?php

namespace App\Domain\Events\Models;

use App\Domain\Events\Models\Concerns\TransitionsStatus;
use App\Domain\Events\Support\HasPublicCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Order extends BaseModel
{
    use HasPublicCode;
    use TransitionsStatus;

    public const CODE_PREFIX = 'ORD';
    public const STATUS_LOOKUP = OrderStatus::class;

    protected $casts = [
        'total_amount' => 'decimal:2',
        'reserved_until' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function buyerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'status_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ── Derivações (contracts/domain-derivations.md) ────────────────

    /** Total efetivamente pago (payments em situação paid). */
    public function amountPaid(): string
    {
        return (string) $this->payments()
            ->whereIn('status_id', PaymentStatus::idsFor([PaymentStatus::PAID]))
            ->sum('amount');
    }

    /** Reserva vencida sem pagamento? */
    public function isExpired(): bool
    {
        return $this->status?->slug === OrderStatus::PENDING
            && $this->reserved_until !== null
            && Carbon::now()->gt($this->reserved_until);
    }
}
