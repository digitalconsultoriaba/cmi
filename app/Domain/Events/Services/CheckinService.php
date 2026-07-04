<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\EventStatus;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Check-in da portaria: atômico sob lock NA LINHA do ticket — validações
 * simultâneas do mesmo código produzem exatamente UMA entrada; tickets
 * diferentes não se serializam (a fila anda). Recusa nunca altera estado.
 */
class CheckinService
{
    /** Situações que valem entrada. */
    private const ELIGIBLE = [TicketStatus::PAID, TicketStatus::CONFIRMED, TicketStatus::COURTESY];

    public function checkIn(string $code, User $operator): Ticket
    {
        $code = strtoupper(trim($code));

        return DB::transaction(function () use ($code, $operator) {
            $ticket = Ticket::query()
                ->where('code', $code)
                ->lockForUpdate()
                ->first();

            if ($ticket === null) {
                throw new NotFoundHttpException('Ingresso não encontrado.');
            }

            if ($ticket->event->status?->slug === EventStatus::CANCELLED) {
                throw new DomainRuleViolation('O evento foi cancelado.', 'event_cancelled');
            }

            $status = $ticket->status?->slug;

            if ($status === TicketStatus::USED) {
                throw new DomainRuleViolation(
                    'Ingresso já utilizado.',
                    'already_used',
                    [
                        'usedAt' => $ticket->used_at?->toISOString(),
                        'validatedBy' => $ticket->validated_by
                            ? User::query()->find($ticket->validated_by)?->name
                            : null,
                    ]
                );
            }

            if (in_array($status, [TicketStatus::CANCELLED, TicketStatus::REFUNDED], true)) {
                throw new DomainRuleViolation('Ingresso cancelado.', 'ticket_cancelled');
            }

            if ($status === TicketStatus::TRANSFERRED) {
                throw new DomainRuleViolation(
                    'Ingresso transferido — existe um ingresso novo válido.',
                    'ticket_transferred',
                    ['transferredToCode' => $ticket->transferredTo?->code]
                );
            }

            if (! in_array($status, self::ELIGIBLE, true)) {
                throw new DomainRuleViolation(
                    'Pagamento pendente — o ingresso ainda não vale entrada.',
                    'not_paid'
                );
            }

            $ticket->forceFill([
                'used_at' => now(),
                'validated_by' => $operator->id,
            ]);
            $ticket->transitionTo(TicketStatus::USED);

            activity('ticket.checked_in')
                ->performedOn($ticket)
                ->causedBy($operator)
                ->withProperties(['reference' => $ticket->code])
                ->log('Check-in do ingresso '.$ticket->code.' ('.$ticket->participant_name.')');

            return $ticket->fresh(['ticketType', 'status']);
        });
    }
}
