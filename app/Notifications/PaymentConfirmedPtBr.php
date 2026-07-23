<?php

namespace App\Notifications;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\PaymentStatus;
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
        $base = rtrim((string) config('app.frontend_url'), '/');
        $payment = $this->order->payments()
            ->whereIn('status_id', PaymentStatus::idsFor([PaymentStatus::PAID]))
            ->latest('paid_at')->first();

        $message = (new MailMessage)
            ->subject('Pagamento confirmado — '.$this->order->event?->name)
            ->view('emails.payment-confirmed', [
                'order' => $this->order->loadMissing('event', 'tickets.ticketType', 'tickets.ticketLot'),
                'payment' => $payment,
                'eventName' => $this->order->event?->name,
                'logoUrl' => $base.'/favicon-192x192.png',
                'entrarUrl' => $base.'/entrar',
                'trackUrl' => $base.'/acompanhar',
            ]);

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

        return $message;
    }
}
