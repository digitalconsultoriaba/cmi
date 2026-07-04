<?php

namespace App\Notifications;

use App\Domain\Events\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmedPtBr extends Notification
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
        $message = (new MailMessage)
            ->subject('Pagamento confirmado — '.$this->order->event->name)
            ->greeting('Olá, '.$this->order->buyer_name.'!')
            ->line('Recebemos o seu pagamento e seus ingressos estão confirmados. 🎉')
            ->line('Pedido: '.$this->order->code);

        foreach ($this->order->tickets as $ticket) {
            $message->line('• '.$ticket->participant_name.' — '.$ticket->ticketType?->name
                .' ('.$ticket->code.')');
        }

        return $message
            ->action('Ver meus ingressos', config('app.frontend_url').'/minha-conta/ingressos')
            ->line('Os comprovantes com QR code já estão disponíveis na sua conta.')
            ->salutation('Plataforma de Eventos');
    }
}
