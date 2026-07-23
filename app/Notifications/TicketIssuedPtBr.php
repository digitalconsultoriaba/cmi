<?php

namespace App\Notifications;

use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Services\TicketReceiptPdf;
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

        $message = (new MailMessage)
            ->subject('Seu ingresso — '.$ticket->event?->name)
            ->view('emails.ticket-issued', [
                'ticket' => $ticket,
                'qrDataUri' => 'data:image/svg+xml;base64,'.base64_encode($qrSvg),
                'eventName' => $ticket->event?->name,
                'logoUrl' => $base.'/favicon-192x192.png',
                'entrarUrl' => $base.'/entrar',
            ]);

        // Anexa o ingresso em PDF (mesmo do download em Meus Ingressos). Falha
        // no PDF nunca impede o e-mail — o QR inline ainda vale.
        try {
            $message->attachData(
                app(TicketReceiptPdf::class)->bytes($ticket),
                'ingresso-'.$ticket->code.'.pdf',
                ['mime' => 'application/pdf'],
            );
        } catch (\Throwable) {
            // silencioso
        }

        return $message;
    }
}
