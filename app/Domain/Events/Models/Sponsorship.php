<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sponsorship extends BaseModel
{
    protected $casts = ['total_amount' => 'decimal:2'];

    protected $attributes = ['status' => 'pending'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(SponsorshipInstallment::class);
    }

    /**
     * Status geral = f(parcelas) — cache recalculado em transação, nunca editado
     * pela tela (mesmo padrão de sold_count; specs/003 data-model).
     */
    public function recalculateStatus(): string
    {
        if ($this->status === 'cancelled') {
            return $this->status;
        }

        $total = $this->installments()->count();
        $paid = $this->installments()->where('status', 'paid')->count();

        $status = match (true) {
            $total > 0 && $paid === $total => 'paid',
            $paid > 0 => 'partial',
            default => 'pending',
        };

        $this->forceFill(['status' => $status])->save();

        return $status;
    }
}
