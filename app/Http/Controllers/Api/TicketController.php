<?php

namespace App\Http\Controllers\Api;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketStatus;
use App\Domain\Events\Services\TicketReceiptPdf;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $tickets = Ticket::query()
            // Área do participante: só ingressos emitidos/válidos. Cancelados,
            // expirados e reservados (de tentativas abandonadas) não poluem a
            // lista. No painel admin a listagem segue completa (outro controller).
            ->whereHas('status', fn ($q) => $q->whereIn('slug', [
                TicketStatus::PAID, TicketStatus::CONFIRMED, TicketStatus::COURTESY, TicketStatus::USED,
            ]))
            ->where(fn ($q) => $q
                ->where('participant_user_id', $user->id)
                ->orWhere('participant_email', $user->email)
                ->orWhereHas('order', fn ($o) => $o->where('buyer_user_id', $user->id)))
            ->with(['event', 'status', 'ticketType', 'order', 'shirtModel', 'shirtSize'])
            ->latest('id')
            ->get();

        // Claim preguiçoso: ingressos emitidos para meu e-mail ganham meu user_id
        $tickets->each(function (Ticket $ticket) use ($user) {
            if ($ticket->participant_user_id === null
                && $ticket->participant_email !== null
                && mb_strtolower($ticket->participant_email) === $user->email) {
                $ticket->forceFill(['participant_user_id' => $user->id])->save();
            }
        });

        return TicketResource::collection($tickets);
    }

    public function show(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        return TicketResource::make(
            $ticket->load(['event', 'status', 'ticketType', 'order', 'shirtModel', 'shirtSize'])
        );
    }

    public function receipt(Request $request, Ticket $ticket, TicketReceiptPdf $pdf)
    {
        $this->authorize('receipt', $ticket);

        $printable = in_array($ticket->status?->slug, [
            TicketStatus::PAID, TicketStatus::CONFIRMED, TicketStatus::COURTESY, TicketStatus::USED,
        ], true);

        if (! $printable) {
            throw new DomainRuleViolation(
                'O comprovante fica disponível após a confirmação do pagamento.',
                'not_confirmed'
            );
        }

        return $pdf->download($ticket);
    }
}
