<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Criação de conta de acesso no ato da confirmação (spec 015): login = e-mail do
 * pedido, senha temporária gerada. Papel attendee. Enviado a participantes e ao
 * comprador que ainda não tinham conta com senha.
 */
class AccessCreatedPtBr extends Notification
{
    public function __construct(
        private readonly string $password,
        private readonly ?string $eventName = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        return (new MailMessage)
            ->subject('Sua conta de acesso'.($this->eventName !== null ? ' — '.$this->eventName : ''))
            ->view('emails.access-created', [
                'user' => $notifiable,
                'password' => $this->password,
                'eventName' => $this->eventName,
                'logoUrl' => $base.'/favicon-192x192.png',
                'entrarUrl' => $base.'/entrar',
            ]);
    }
}
