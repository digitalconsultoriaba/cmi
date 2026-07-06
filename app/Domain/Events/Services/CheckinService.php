<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\CheckinOrigin;
use App\Domain\Events\Models\EventDay;
use App\Domain\Events\Models\EventStatus;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketDayCheckin;
use App\Domain\Events\Models\TicketStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Check-in da portaria: atômico sob lock NA LINHA do ticket. A partir da spec
 * 012 é POR DIA — único por (ingresso, dia). Em eventos de 1 dia espelha o
 * comportamento atual (used_at/validated_by/status used); em multi-dia o
 * ingresso não vira "used" global (pode ser lido nos demais dias). Recusa nunca
 * altera estado.
 */
class CheckinService
{
    /** Situações que valem entrada. */
    private const ELIGIBLE = [TicketStatus::PAID, TicketStatus::CONFIRMED, TicketStatus::COURTESY];

    /** Check-in do ingresso num dia específico do evento. */
    public function checkInDay(string $code, EventDay $day, User $operator, string $origin = CheckinOrigin::QR, ?string $note = null): TicketDayCheckin
    {
        $code = strtoupper(trim($code));

        return DB::transaction(function () use ($code, $day, $operator, $origin, $note) {
            $ticket = Ticket::query()->where('code', $code)->lockForUpdate()->first();

            if ($ticket === null) {
                throw new NotFoundHttpException('Ingresso não encontrado.');
            }
            if ($ticket->event_id !== $day->event_id) {
                throw new DomainRuleViolation('Ingresso não pertence a este evento.', 'wrong_event');
            }
            if ($ticket->event->status?->slug === EventStatus::CANCELLED) {
                throw new DomainRuleViolation('O evento foi cancelado.', 'event_cancelled');
            }
            if ($day->isFinished()) {
                throw new DomainRuleViolation('Dia finalizado — reabra para alterar.', 'day_finished');
            }
            if ($day->isBlocked()) {
                throw new DomainRuleViolation('Dia bloqueado para check-in.', 'day_blocked');
            }

            // Já tem presença NESTE dia? (único por ingresso+dia)
            $existing = TicketDayCheckin::query()
                ->where('ticket_id', $ticket->id)
                ->where('event_day_id', $day->id)
                ->first();
            if ($existing !== null) {
                throw new DomainRuleViolation(
                    'Participante já possui check-in registrado neste dia.',
                    'already_checked_in_day',
                    [
                        'checkedInAt' => $existing->checked_in_at?->toISOString(),
                        'operator' => $existing->operator_id
                            ? User::query()->find($existing->operator_id)?->name : null,
                    ]
                );
            }

            $status = $ticket->status?->slug;

            // Legado/1-dia: ingresso já "utilizado" sem registro por dia.
            if ($status === TicketStatus::USED) {
                throw new DomainRuleViolation(
                    'Participante já possui check-in registrado neste dia.',
                    'already_checked_in_day',
                    [
                        'checkedInAt' => $ticket->used_at?->toISOString(),
                        'operator' => $ticket->validated_by
                            ? User::query()->find($ticket->validated_by)?->name : null,
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

            $checkin = TicketDayCheckin::query()->create([
                'event_id' => $day->event_id,
                'event_day_id' => $day->id,
                'ticket_id' => $ticket->id,
                'checked_in_at' => now(),
                'operator_id' => $operator->id,
                'origin' => $origin,
                'note' => $note,
            ]);

            // Compatibilidade 1 dia: espelha o "utilizado" atual no ingresso.
            if ($ticket->event->durationDays() <= 1) {
                $ticket->forceFill(['used_at' => now(), 'validated_by' => $operator->id]);
                $ticket->transitionTo(TicketStatus::USED);
            }

            activity('ticket.checked_in')
                ->performedOn($ticket)
                ->causedBy($operator)
                ->withProperties(['reference' => $ticket->code, 'dayNumber' => $day->day_number])
                ->log('Check-in do ingresso '.$ticket->code.' ('.$ticket->participant_name.') — Dia '.$day->day_number);

            return $checkin->load('eventDay');
        });
    }

    /**
     * Compat: check-in "do evento" (sem dia explícito). Resolve o único dia em
     * eventos de 1 dia; em multi-dia exige o dia (use checkInDay).
     */
    public function checkIn(string $code, User $operator): Ticket
    {
        $code = strtoupper(trim($code));
        $ticket = Ticket::query()->where('code', $code)->first();
        if ($ticket === null) {
            throw new NotFoundHttpException('Ingresso não encontrado.');
        }

        $days = $ticket->event->eventDays()->get();
        if ($days->count() !== 1) {
            throw new DomainRuleViolation('Selecione o dia do evento para o check-in.', 'day_required');
        }

        $this->checkInDay($code, $days->first(), $operator);

        return $ticket->fresh(['ticketType', 'status']);
    }
}
