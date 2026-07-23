<?php

namespace App\Notifications;

use App\Domain\Events\Models\Ticket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Ingresso do participante + acesso passwordless à sua área (spec 014).
 */
class TicketIssuedPtBr extends Notification
{
    public function __construct(private readonly Ticket $ticket)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $ticket = $this->ticket->loadMissing('event', 'ticketType', 'ticketLot');

        // QR de validação (URL /validar/{code}); a portaria confere no check-in.
        $qrSvg = QrCode::format('svg')->size(196)->margin(0)->errorCorrection('M')
            ->generate($base.'/validar/'.$ticket->code);

        return (new MailMessage)
            ->subject('Seu ingresso — '.$ticket->event?->name)
            ->view('emails.ticket-issued', [
                'ticket' => $ticket,
                'qrDataUri' => 'data:image/svg+xml;base64,'.base64_encode($qrSvg),
                'eventName' => $ticket->event?->name,
                'logoUrl' => $base.'/favicon-192x192.png',
                'entrarUrl' => $base.'/entrar',
            ]);
    }
}
