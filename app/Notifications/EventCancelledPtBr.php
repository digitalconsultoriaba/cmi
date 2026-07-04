<?php

namespace App\Notifications;

use App\Domain\Events\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventCancelledPtBr extends Notification
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
        $event = $this->order->event;

        $message = (new MailMessage)
            ->subject('Evento cancelado — '.$event->name)
            ->greeting('Olá, '.$this->order->buyer_name.'!')
            ->line('Infelizmente o evento "'.$event->name.'" foi cancelado.')
            ->line('Seu pedido '.$this->order->code.' foi cancelado automaticamente.');

        if ($event->cancel_reason) {
            $message->line('Motivo informado pela organização: '.$event->cancel_reason);
        }

        return $message
            ->line('Se houver pagamento confirmado, a devolução INTEGRAL já está na fila da nossa tesouraria — você receberá a confirmação por e-mail.')
            ->salutation('Plataforma de Eventos');
    }
}
