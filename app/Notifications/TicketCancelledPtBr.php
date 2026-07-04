<?php

namespace App\Notifications;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Ticket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCancelledPtBr extends Notification
{
    public function __construct(
        private readonly ?Ticket $ticket,
        private readonly ?string $refundAmount = null,
        private readonly ?Order $order = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->ticket !== null
            ? 'Ingresso cancelado — '.$this->ticket->event->name
            : 'Pedido cancelado — '.$this->order->event->name;

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Olá!')
            ->line($this->ticket !== null
                ? 'O ingresso '.$this->ticket->code.' ('.$this->ticket->participant_name.') foi cancelado.'
                : 'O pedido '.$this->order->code.' foi cancelado com todos os seus ingressos.');

        if ($this->refundAmount !== null) {
            $message->line('Devolução a processar: R$ '
                .number_format((float) $this->refundAmount, 2, ',', '.')
                .' — nossa tesouraria fará o reembolso e você receberá a confirmação por e-mail.');
        }

        return $message->salutation('Plataforma de Eventos');
    }
}
