<?php

namespace App\Notifications;

use App\Domain\Events\Models\SupportCase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundCompletedPtBr extends Notification
{
    public function __construct(private readonly SupportCase $case)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Devolução efetuada — pedido '.$this->case->order?->code)
            ->greeting('Olá!')
            ->line('A devolução referente ao pedido '.$this->case->order?->code.' foi efetuada.')
            ->line('Valor: R$ '.number_format((float) $this->case->refund_amount, 2, ',', '.'))
            ->line('Dependendo do meio de pagamento, o valor pode levar alguns dias para aparecer.')
            ->salutation('Plataforma de Eventos');
    }
}
