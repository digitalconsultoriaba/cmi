<?php

namespace App\Http\Controllers\Api\Gate;

use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketStatus;
use App\Domain\Events\Services\CheckinService;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class GateController extends Controller
{
    public function checkin(Request $request, CheckinService $service)
    {
        $data = $request->validate(
            ['code' => ['required', 'string', 'max:30']],
            ['code.required' => 'Informe o código do ingresso.']
        );

        $ticket = $service->checkIn($data['code'], $request->user());

        return ApiResponse::data([
            'code' => $ticket->code,
            'participantName' => $ticket->participant_name,
            'companionName' => $ticket->companion_name,
            'ticketTypeName' => $ticket->ticketType?->name,
            'seats' => $this->seats($ticket),
            'usedAt' => $ticket->used_at?->toISOString(),
        ]);
    }

    public function attendance(Request $request)
    {
        $eligibleIds = TicketStatus::idsFor([
            TicketStatus::PAID, TicketStatus::CONFIRMED, TicketStatus::COURTESY, TicketStatus::USED,
        ]);
        $usedId = TicketStatus::idFor(TicketStatus::USED);

        $query = Ticket::query()
            ->whereIn('status_id', $eligibleIds)
            ->with(['ticketType', 'status'])
            ->orderBy('participant_name');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q
                ->where('participant_name', 'like', "%{$search}%")
                ->orWhere('companion_name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"));
        }

        $tickets = $query->get();

        // Contadores em PESSOAS (casal conta 2) — derivação, nunca coluna
        $people = fn ($collection) => (int) $collection->sum(
            fn (Ticket $ticket) => $this->seats($ticket)
        );

        $expected = $people($tickets);
        $present = $people($tickets->where('status_id', $usedId));

        $validators = \App\Models\User::query()
            ->whereIn('id', $tickets->pluck('validated_by')->filter()->unique())
            ->pluck('name', 'id');

        return ApiResponse::data([
            'expectedPeople' => $expected,
            'presentPeople' => $present,
            'absentPeople' => $expected - $present,
            'tickets' => $tickets->map(fn (Ticket $ticket) => [
                'code' => $ticket->code,
                'participantName' => $ticket->participant_name,
                'companionName' => $ticket->companion_name,
                'ticketTypeName' => $ticket->ticketType?->name,
                'seats' => $this->seats($ticket),
                'status' => $ticket->status?->slug,
                'usedAt' => $ticket->used_at?->toISOString(),
                'validatedBy' => $ticket->validated_by
                    ? ($validators[$ticket->validated_by] ?? null)
                    : null,
            ])->values(),
        ]);
    }

    private function seats(Ticket $ticket): int
    {
        $type = $ticket->ticketType;

        return max((int) ($type?->seats_per_ticket ?? 1), $type?->is_couple ? 2 : 1);
    }
}
