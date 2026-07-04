<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Lançamento financeiro (conta a pagar OU a receber) — spec 010.
 * Situação DERIVADA (nunca coluna); settled_amount é cache recontável.
 */
class FinancialEntry extends BaseModel
{
    protected $casts = [
        'amount' => 'decimal:2',
        'settled_amount' => 'decimal:2',
        'due_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    // direções
    public const PAYABLE = 'payable';
    public const RECEIVABLE = 'receivable';

    // situações derivadas
    public const OPEN = 'open';
    public const PARTIAL = 'partial';
    public const SETTLED = 'settled';      // pago (payable) / recebido (receivable)
    public const OVERDUE = 'overdue';
    public const CANCELLED = 'cancelled';

    // origens
    public const ORIGINS = [
        'manual', 'ticket', 'sponsorship', 'inscription', 'event_expense',
        'admin_expense', 'admin_income', 'adjustment', 'other',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'category_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(FinancialPerson::class, 'person_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(FinancialPaymentMethod::class, 'payment_method_id');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(FinancialSettlement::class, 'entry_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FinancialAttachment::class, 'entry_id');
    }

    /** Saldo restante (piso zero). */
    public function balance(): string
    {
        $balance = bcsub((string) $this->amount, (string) $this->settled_amount, 2);

        return bccomp($balance, '0.00', 2) === -1 ? '0.00' : $balance;
    }

    /** Situação derivada — nunca armazenada (princípio II). */
    public function status(): string
    {
        if ($this->cancelled_at !== null) {
            return self::CANCELLED;
        }
        if (bccomp((string) $this->settled_amount, (string) $this->amount, 2) >= 0
            && bccomp((string) $this->amount, '0.00', 2) === 1) {
            return self::SETTLED;
        }
        if (bccomp((string) $this->settled_amount, '0.00', 2) === 1) {
            return self::PARTIAL;
        }
        if ($this->due_date !== null && $this->due_date->isPast()
            && ! $this->due_date->isToday()) {
            return self::OVERDUE;
        }

        return self::OPEN;
    }

    /** Rótulo pt-BR conforme direção. */
    public function statusLabel(): string
    {
        $receivable = $this->direction === self::RECEIVABLE;

        return match ($this->status()) {
            self::CANCELLED => 'Cancelado',
            self::SETTLED => $receivable ? 'Recebido' : 'Pago',
            self::PARTIAL => $receivable ? 'Recebido parcialmente' : 'Pago parcialmente',
            self::OVERDUE => 'Vencido',
            default => 'Em aberto',
        };
    }

    /** Espelho de ingresso/patrocínio → somente leitura na UI. */
    public function isMirror(): bool
    {
        return $this->source_type !== null;
    }

    /** Recontagem do cache a partir das baixas (payment+receipt − reversal). */
    public function recountSettled(): string
    {
        $in = (string) $this->settlements()
            ->whereIn('kind', [FinancialSettlement::PAYMENT, FinancialSettlement::RECEIPT])
            ->sum('amount');
        $out = (string) $this->settlements()
            ->where('kind', FinancialSettlement::REVERSAL)->sum('amount');

        $net = bcsub($in, $out, 2);
        if (bccomp($net, '0.00', 2) === -1) {
            $net = '0.00';
        }

        $this->forceFill(['settled_amount' => $net])->save();

        return $net;
    }
}
