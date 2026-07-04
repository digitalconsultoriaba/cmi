<?php

namespace App\Notifications;

use App\Domain\Events\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BoletoIssuedPtBr extends Notification
{
    public function __construct(private readonly Payment $payment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->payment->order;

        return (new MailMessage)
            ->subject('Seu boleto — '.$order->event->name)
            ->greeting('Olá, '.$order->buyer_name.'!')
            ->line('Aqui estão os dados para pagamento do pedido '.$order->code.':')
            ->line('**Linha digitável:** '.$this->payment->boleto_line)
            ->line('Você também pode pagar na hora pelo Pix (QR code na página do pedido).')
            ->line('Vencimento: '.$this->payment->due_date?->format('d/m/Y'))
            ->action('Ver o pedido', config('app.frontend_url').'/pedido/'.$order->code.'/pagar')
            ->line('Após a compensação, seus ingressos serão confirmados automaticamente.')
            ->salutation('Plataforma de Eventos');
    }
}
