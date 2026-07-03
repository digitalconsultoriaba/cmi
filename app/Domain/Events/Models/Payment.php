<?php

namespace App\Domain\Events\Models;

use App\Domain\Events\Models\Concerns\TransitionsStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro financeiro: sem soft delete (correção = novo registro).
 * Idempotência garantida pelo unique (provider, provider_charge_id).
 */
class Payment extends BaseAuditedModel
{
    use TransitionsStatus;

    public const STATUS_LOOKUP = PaymentStatus::class;

    public const METHODS = ['pix', 'boleto', 'card', 'manual'];
    public const PROVIDERS = ['sicoob', 'card_gateway', 'manual'];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'raw_response' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(PaymentStatus::class, 'status_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
