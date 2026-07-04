<?php

namespace App\Notifications;

use App\Domain\Events\Models\Ticket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketTransferredPtBr extends Notification
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
        return (new MailMessage)
            ->subject('Você recebeu um ingresso — '.$this->ticket->event->name)
            ->greeting('Olá, '.$this->ticket->participant_name.'!')
            ->line('Um ingresso do evento "'.$this->ticket->event->name.'" foi transferido para você.')
            ->line('Código do seu ingresso: '.$this->ticket->code)
            ->line('Crie uma conta (ou entre) com este e-mail para ver o ingresso e baixar o comprovante com QR code.')
            ->action('Acessar meus ingressos', config('app.frontend_url').'/minha-conta/ingressos')
            ->salutation('Plataforma de Eventos');
    }
}
