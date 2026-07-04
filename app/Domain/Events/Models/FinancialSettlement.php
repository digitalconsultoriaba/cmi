<?php

namespace App\Domain\Events\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baixa/movimentação de dinheiro (pagamento/recebimento/estorno) — registro
 * imutável (correção = novo registro), sem soft delete.
 */
class FinancialSettlement extends BaseAuditedModel
{
    protected $casts = ['settled_on' => 'date', 'amount' => 'decimal:2'];

    public const PAYMENT = 'payment';
    public const RECEIPT = 'receipt';
    public const REVERSAL = 'reversal';

    public function entry(): BelongsTo
    {
        return $this->belongsTo(FinancialEntry::class, 'entry_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(FinancialPaymentMethod::class, 'payment_method_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
