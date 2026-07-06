<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketDayCheckin;
use App\Domain\Events\Models\TicketStatus;

/**
 * Relatório de presença por dia (spec 012): por dia + consolidado + individual.
 * Conta por INGRESSO (linha), coerente com o painel; derivado de
 * ticket_day_checkins.
 */
class AttendanceReportService
{
    private const ELIGIBLE = [
        TicketStatus::PAID, TicketStatus::CONFIRMED, TicketStatus::COURTESY, TicketStatus::USED,
    ];

    public function report(Event $event): array
    {
        $days = $event->eventDays()->orderBy('day_number')->get();

        $tickets = Ticket::query()
            ->where('event_id', $event->id)
            ->whereIn('status_id', TicketStatus::idsFor(self::ELIGIBLE))
            ->with(['ticketType', 'status'])
            ->orderBy('participant_name')
            ->get();

        // Check-ins do evento, agrupados por ticket → [ticket_id => [day_id => checkin]]
        $checkins = TicketDayCheckin::query()
            ->where('event_id', $event->id)
            ->whereIn('ticket_id', $tickets->pluck('id'))
            ->with('operator')
            ->get()
            ->groupBy('ticket_id')
            ->map(fn ($rows) => $rows->keyBy('event_day_id'));

        $total = $tickets->count();

        // Por dia
        $byDay = $days->map(function ($day) use ($tickets, $checkins, $total) {
            $present = $tickets->filter(fn ($t) => ($checkins[$t->id] ?? collect())->has($day->id))->count();

            return [
                'dayNumber' => (int) $day->day_number,
                'date' => $day->event_date?->toDateString(),
                'label' => $day->label,
                'present' => $present,
                'absent' => $total - $present,
                'presentPct' => $total > 0 ? number_format($present / $total * 100, 2, '.', '') : '0.00',
            ];
        })->values();

        // Consolidado (por ingresso)
        $dayIds = $days->pluck('id');
        $allDays = 0;
        $partial = 0;
        $none = 0;
        foreach ($tickets as $t) {
            $set = $checkins[$t->id] ?? collect();
            $count = $dayIds->filter(fn ($id) => $set->has($id))->count();
            if ($count === 0) {
                $none++;
            } elseif ($count === $days->count()) {
                $allDays++;
            } else {
                $partial++;
            }
        }

        // Individual
        $individual = $tickets->map(function ($t) use ($days, $checkins) {
            $set = $checkins[$t->id] ?? collect();

            return [
                'code' => $t->code,
                'participantName' => $t->participant_name,
                'ticketTypeName' => $t->ticketType?->name,
                'ticketStatus' => $t->status?->slug,
                'days' => $days->map(function ($day) use ($set) {
                    $c = $set->get($day->id);

                    return [
                        'dayNumber' => (int) $day->day_number,
                        'present' => $c !== null,
                        'checkedInAt' => $c?->checked_in_at?->toISOString(),
                        'operator' => $c?->operator?->name,
                    ];
                })->values(),
            ];
        })->values();

        return [
            'totalRegistered' => $total,
            'days' => $days->map(fn ($d) => [
                'dayNumber' => (int) $d->day_number, 'date' => $d->event_date?->toDateString(), 'label' => $d->label,
            ])->values(),
            'byDay' => $byDay,
            'consolidated' => ['allDays' => $allDays, 'partial' => $partial, 'none' => $none],
            'individual' => $individual,
        ];
    }
}
