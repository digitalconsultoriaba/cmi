<?php

namespace App\Notifications;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Services\MagicLinkService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Acesso do comprador (guest) ao back-office com todos os ingressos do pedido,
 * por link mágico passwordless (spec 014).
 */
class OrderAccessPtBr extends Notification
{
    public function __construct(private readonly Order $order)
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

        $message = (new MailMessage)
            ->subject('Sua inscrição — '.$this->order->event?->name)
            ->greeting('Olá, '.$this->order->buyer_name.'!')
            ->line('Recebemos sua inscrição. Pedido: '.$this->order->code)
            ->line('Participantes:');

        foreach ($this->order->tickets as $ticket) {
            $message->line('• '.$ticket->participant_name.' — '.$ticket->code
                .($ticket->is_courtesy ? ' (gratuito)' : ''));
        }

        return $message
            ->action('Acessar meus ingressos', $access)
            ->line('Use o botão acima para acessar sua área com todos os ingressos.')
            ->salutation('Plataforma de Eventos');
    }
}
