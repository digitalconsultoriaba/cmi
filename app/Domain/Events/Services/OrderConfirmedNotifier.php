<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\Order;
use App\Notifications\OrderAccessPtBr;
use App\Notifications\TicketIssuedPtBr;
use Illuminate\Support\Facades\Log;

/**
 * Entrega pós-confirmação (spec 014): cada participante com conta própria recebe
 * o SEU ingresso + magic link; o comprador guest (sem senha) recebe acesso ao
 * back-office. Aditivo e defensivo — não altera o e-mail de pagamento existente
 * (specs 004/005): só age sobre participantes com conta distinta e compradores
 * passwordless. Falha de e-mail nunca bloqueia o fluxo.
 */
class OrderConfirmedNotifier
{
    public function notify(Order $order): void
    {
        $order->loadMissing('tickets.participantUser', 'tickets.ticketType', 'tickets.event', 'buyerUser', 'event');

        foreach ($order->tickets as $ticket) {
            $participant = $ticket->participantUser;
            if ($participant === null || $participant->id === $order->buyer_user_id) {
                continue; // sem conta própria, ou é o próprio comprador
            }
            $this->safeNotify(fn () => $participant->notify(new TicketIssuedPtBr($ticket)), $ticket->code);
        }

        $buyer = $order->buyerUser;
        if ($buyer !== null && $buyer->password === null) {
            $this->safeNotify(fn () => $buyer->notify(new OrderAccessPtBr($order)), $order->code);
        }
    }

    private function safeNotify(callable $send, string $ref): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar e-mail de acesso/ingresso', ['ref' => $ref, 'error' => $e->getMessage()]);
        }
    }
}
