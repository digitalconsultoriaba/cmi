<?php

namespace App\Domain\Events\Models;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Support\HasPublicCode;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtesyVoucher extends BaseModel
{
    use HasPublicCode;

    public const CODE_PREFIX = 'CTY';

    public const AVAILABLE = 'available';
    public const DISTRIBUTED = 'distributed';
    public const REDEEMED = 'redeemed';

    /** Ciclo só avança (contracts/domain-derivations.md). */
    public const FLOW = [self::AVAILABLE, self::DISTRIBUTED, self::REDEEMED];

    protected $casts = [
        'distributed_at' => 'datetime',
        'redeemed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function redeemedTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'redeemed_ticket_id');
    }

    public function transitionTo(string $status): static
    {
        $from = array_search($this->status, self::FLOW, true);
        $to = array_search($status, self::FLOW, true);

        if ($to === false) {
            throw new DomainRuleViolation("Situação '$status' inexistente para voucher.", 'invalid_status');
        }

        if ($from === false || $to <= $from) {
            throw new DomainRuleViolation(
                "Voucher em '{$this->status}' não pode voltar/repetir situação.",
                'terminal_status'
            );
        }

        $this->forceFill(['status' => $status])->save();

        return $this;
    }
}
