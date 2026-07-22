<?php

namespace App\Notifications;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Services\OrderReceiptPdf;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

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

        // Anexa o comprovante de compra em PDF (falha nunca impede o e-mail).
        try {
            $message->attachData(
                app(OrderReceiptPdf::class)->bytes($this->order),
                'comprovante-'.$this->order->code.'.pdf',
                ['mime' => 'application/pdf'],
            );
        } catch (\Throwable $e) {
            Log::warning('Falha ao anexar comprovante ao e-mail', [
                'order' => $this->order->code, 'error' => $e->getMessage(),
            ]);
        }

        return $message
            ->action('Ver meus ingressos', config('app.frontend_url').'/minha-conta/ingressos')
            ->line('O comprovante de compra está anexado a este e-mail.')
            ->salutation('Plataforma de Eventos');
    }
}
