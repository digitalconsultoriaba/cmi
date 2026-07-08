<?php

namespace App\Notifications;

use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Services\MagicLinkService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
        $access = $notifiable instanceof \App\Models\User
            ? app(MagicLinkService::class)->linkFor($notifiable)
            : config('app.frontend_url').'/entrar';

        return (new MailMessage)
            ->subject('Sua inscrição — '.$this->ticket->event?->name)
            ->greeting('Olá, '.$this->ticket->participant_name.'!')
            ->line('Sua inscrição está confirmada. 🎉')
            ->line('Ingresso: '.$this->ticket->code
                .($this->ticket->is_courtesy ? ' (gratuito por voucher)' : ''))
            ->action('Acessar meu ingresso', $access)
            ->line('O comprovante com QR code está disponível na sua área.')
            ->salutation('Plataforma de Eventos');
    }
}
