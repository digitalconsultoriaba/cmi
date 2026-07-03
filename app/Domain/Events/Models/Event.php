<?php

namespace App\Domain\Events\Models;

use App\Domain\Events\Models\Concerns\TransitionsStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Event extends BaseModel
{
    use TransitionsStatus;

    public const STATUS_LOOKUP = EventStatus::class;

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'sales_start_at' => 'datetime',
        'sales_end_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'allow_card' => 'boolean',
        'allow_boleto' => 'boolean',
        'allow_pix' => 'boolean',
        'allow_shirt_choice' => 'boolean',
        'requires_shirt' => 'boolean',
        'allow_kit' => 'boolean',
        'allow_transfer' => 'boolean',
        'allow_user_cancel' => 'boolean',
        'allow_refund_request' => 'boolean',
        'allow_courtesy' => 'boolean',
    ];

    // ── Relacionamentos ─────────────────────────────────────────────

    public function status(): BelongsTo
    {
        return $this->belongsTo(EventStatus::class, 'status_id');
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    public function landingBlocks(): HasMany
    {
        return $this->hasMany(LandingBlock::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function ticketLots(): HasMany
    {
        return $this->hasMany(TicketLot::class);
    }

    public function shirtModels(): HasMany
    {
        return $this->hasMany(EventShirtModel::class);
    }

    public function shirtSizes(): HasMany
    {
        return $this->hasMany(EventShirtSize::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function courtesyVouchers(): HasMany
    {
        return $this->hasMany(CourtesyVoucher::class);
    }

    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class);
    }

    public function supportCases(): HasMany
    {
        return $this->hasMany(SupportCase::class);
    }

    // ── Derivações (contracts/domain-derivations.md — nunca colunas) ──

    /** Tickets que ocupam vaga (vivos + used). */
    public function ticketsSold(): int
    {
        return $this->tickets()
            ->whereIn('status_id', TicketStatus::idsFor(TicketStatus::COUNTS_CAPACITY))
            ->count();
    }

    /** Vagas restantes do evento; null = capacidade ilimitada. */
    public function available(): ?int
    {
        if ($this->total_capacity === null) {
            return null;
        }

        return $this->total_capacity - $this->ticketsSold();
    }

    public function soldOut(): bool
    {
        $available = $this->available();

        return $available !== null && $available <= 0;
    }

    /** Lotes elegíveis agora (ativos, na janela, não esgotados), em ordem determinística. */
    public function eligibleLots(): Builder
    {
        $now = Carbon::now();

        return TicketLot::query()
            ->where('event_id', $this->id)
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->where(fn (Builder $q) => $q->whereNull('quantity')->orWhereColumn('sold_count', '<', 'quantity'))
            ->orderBy('sort')
            ->orderBy('id');
    }

    /**
     * Lote vigente: específico do tipo tem precedência sobre o global;
     * sem tipo, considera apenas lotes globais.
     */
    public function currentLot(?TicketType $ticketType = null): ?TicketLot
    {
        if ($ticketType !== null) {
            $specific = $this->eligibleLots()->where('ticket_type_id', $ticketType->id)->first();

            if ($specific !== null) {
                return $specific;
            }
        }

        return $this->eligibleLots()->whereNull('ticket_type_id')->first();
    }

    /** Inscrições abertas — sempre derivado, nunca campo editável. */
    public function salesOpen(): bool
    {
        $now = Carbon::now();

        if ($this->status?->slug !== EventStatus::PUBLISHED) {
            return false;
        }

        if ($this->sales_start_at !== null && $now->lt($this->sales_start_at)) {
            return false;
        }

        if ($this->sales_end_at !== null && $now->gt($this->sales_end_at)) {
            return false;
        }

        if (! $this->eligibleLots()->exists()) {
            return false;
        }

        return ! $this->soldOut();
    }
}
