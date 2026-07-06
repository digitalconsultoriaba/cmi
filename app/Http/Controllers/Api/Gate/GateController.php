<?php

namespace App\Http\Controllers\Api\Gate;

use App\Domain\Events\Models\CheckinOrigin;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventDay;
use App\Domain\Events\Models\EventStatus;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketDayCheckin;
use App\Domain\Events\Models\TicketStatus;
use App\Domain\Events\Services\CheckinService;
use App\Domain\Events\Services\EventDayService;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class GateController extends Controller
{
    /** Eventos disponíveis para a portaria + seus dias (spec 012). */
    public function events()
    {
        $events = Event::query()
            ->whereHas('status', fn ($q) => $q->whereIn('slug', [EventStatus::PUBLISHED, EventStatus::FINISHED]))
            ->with(['eventDays' => fn ($q) => $q->withCount('checkins')])
            ->orderByDesc('starts_at')
            ->get();

        return ApiResponse::data($events->map(fn (Event $e) => [
            'id' => $e->id,
            'name' => $e->name,
            'startsAt' => $e->starts_at?->toISOString(),
            'days' => $e->eventDays->map(fn (EventDay $d) => $this->dayPayload($d))->values(),
        ])->values());
    }

    /**
     * Check-in do ingresso no dia selecionado. `day` é opcional: sem ele, em
     * eventos de 1 dia o único dia é assumido (compatibilidade spec 007).
     */
    public function checkin(Request $request, CheckinService $service)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'day' => ['nullable', 'integer', 'exists:event_days,id'],
            'origin' => ['sometimes', 'in:'.implode(',', CheckinOrigin::ALL)],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['code.required' => 'Informe o código do ingresso.']);

        if (! empty($data['day'])) {
            $day = EventDay::query()->findOrFail($data['day']);
        } else {
            // Resolve o único dia do evento do ingresso (1 dia); multi-dia exige o dia.
            $ticket = Ticket::query()->where('code', strtoupper(trim($data['code'])))->firstOrFail();
            $days = $ticket->event->eventDays()->get();
            abort_if($days->count() !== 1, 422, 'Selecione o dia do evento.');
            $day = $days->first();
        }

        $checkin = $service->checkInDay(
            $data['code'], $day, $request->user(),
            $data['origin'] ?? CheckinOrigin::QR, $data['note'] ?? null
        );
        $ticket = $checkin->ticket;

        return ApiResponse::data([
            'code' => $ticket->code,
            'participantName' => $ticket->participant_name,
            'companionName' => $ticket->companion_name,
            'ticketTypeName' => $ticket->ticketType?->name,
            'seats' => $this->seats($ticket),
            'dayNumber' => $day->day_number,
            'checkedInAt' => $checkin->checked_in_at?->toISOString(),
            'usedAt' => $checkin->checked_in_at?->toISOString(), // alias compat (spec 007)
        ]);
    }

    /** Finaliza um dia (portaria/admin). */
    public function finalizeDay(Request $request, EventDay $day, EventDayService $service)
    {
        return ApiResponse::data($this->dayPayload($service->finalize($day, $request->user())->loadCount('checkins')));
    }

    /**
     * Presença. Com `day` (spec 012) → presença DAQUELE dia; sem `day` cai no
     * modo legado (presença = status "used"), opcionalmente escopado por `event`.
     */
    public function attendance(Request $request)
    {
        $data = $request->validate([
            'event' => ['nullable', 'integer'],
            'day' => ['nullable', 'integer', 'exists:event_days,id'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        if (empty($data['day'])) {
            return $this->attendanceLegacy($request, $data);
        }

        $day = EventDay::query()->findOrFail($data['day']);

        $eligibleIds = TicketStatus::idsFor([
            TicketStatus::PAID, TicketStatus::CONFIRMED, TicketStatus::COURTESY, TicketStatus::USED,
        ]);

        $query = Ticket::query()
            ->where('event_id', $day->event_id)
            ->whereIn('status_id', $eligibleIds)
            ->with(['ticketType', 'status'])
            ->orderBy('participant_name');

        if ($search = trim((string) ($data['search'] ?? ''))) {
            $query->where(fn ($q) => $q
                ->where('participant_name', 'like', "%{$search}%")
                ->orWhere('companion_name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"));
        }

        $tickets = $query->get();

        // Presenças DESTE dia, indexadas por ticket
        $checkins = TicketDayCheckin::query()
            ->where('event_day_id', $day->id)
            ->whereIn('ticket_id', $tickets->pluck('id'))
            ->with('operator')
            ->get()->keyBy('ticket_id');

        $people = fn ($collection) => (int) $collection->sum(fn (Ticket $t) => $this->seats($t));
        $present = $people($tickets->filter(fn (Ticket $t) => $checkins->has($t->id)));
        $expected = $people($tickets);

        return ApiResponse::data([
            'day' => $this->dayPayload($day->loadCount('checkins')),
            'expectedPeople' => $expected,
            'presentPeople' => $present,
            'absentPeople' => $expected - $present,
            'tickets' => $tickets->map(function (Ticket $t) use ($checkins) {
                $c = $checkins->get($t->id);

                return [
                    'code' => $t->code,
                    'participantName' => $t->participant_name,
                    'companionName' => $t->companion_name,
                    'ticketTypeName' => $t->ticketType?->name,
                    'seats' => $this->seats($t),
                    'present' => $c !== null,
                    'checkedInAt' => $c?->checked_in_at?->toISOString(),
                    'operator' => $c?->operator?->name,
                ];
            })->values(),
        ]);
    }

    /** Modo legado (spec 007): presença = status "used", sem dia. */
    private function attendanceLegacy(Request $request, array $data)
    {
        $usedId = TicketStatus::idFor(TicketStatus::USED);
        $eligibleIds = TicketStatus::idsFor([
            TicketStatus::PAID, TicketStatus::CONFIRMED, TicketStatus::COURTESY, TicketStatus::USED,
        ]);

        $query = Ticket::query()
            ->whereIn('status_id', $eligibleIds)
            ->with(['ticketType', 'status'])
            ->orderBy('participant_name');

        if (! empty($data['event'])) {
            $query->where('event_id', $data['event']);
        }
        if ($search = trim((string) ($data['search'] ?? ''))) {
            $query->where(fn ($q) => $q
                ->where('participant_name', 'like', "%{$search}%")
                ->orWhere('companion_name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"));
        }

        $tickets = $query->get();
        $people = fn ($c) => (int) $c->sum(fn (Ticket $t) => $this->seats($t));
        $expected = $people($tickets);
        $present = $people($tickets->where('status_id', $usedId));

        $validators = \App\Models\User::query()
            ->whereIn('id', $tickets->pluck('validated_by')->filter()->unique())
            ->pluck('name', 'id');

        return ApiResponse::data([
            'expectedPeople' => $expected,
            'presentPeople' => $present,
            'absentPeople' => $expected - $present,
            'tickets' => $tickets->map(fn (Ticket $t) => [
                'code' => $t->code,
                'participantName' => $t->participant_name,
                'companionName' => $t->companion_name,
                'ticketTypeName' => $t->ticketType?->name,
                'seats' => $this->seats($t),
                'status' => $t->status?->slug,
                'usedAt' => $t->used_at?->toISOString(),
                'validatedBy' => $t->validated_by ? ($validators[$t->validated_by] ?? null) : null,
            ])->values(),
        ]);
    }

    private function dayPayload(EventDay $d): array
    {
        return [
            'id' => $d->id,
            'dayNumber' => (int) $d->day_number,
            'date' => $d->event_date?->toDateString(),
            'label' => $d->label,
            'status' => $d->status(),
            'checkinCount' => $d->checkins_count ?? $d->checkins()->count(),
        ];
    }

    private function seats(Ticket $ticket): int
    {
        $type = $ticket->ticketType;

        return max((int) ($type?->seats_per_ticket ?? 1), $type?->is_couple ? 2 : 1);
    }
}
