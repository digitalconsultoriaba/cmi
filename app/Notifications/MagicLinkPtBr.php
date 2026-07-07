<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Link de acesso passwordless solicitado por e-mail (spec 014). */
class MagicLinkPtBr extends Notification
{
    public function __construct(private readonly string $link)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Seu acesso à plataforma')
            ->greeting('Olá!')
            ->line('Use o botão abaixo para acessar sua área (o link expira em alguns dias).')
            ->action('Acessar minha conta', $this->link)
            ->line('Se você não solicitou este acesso, ignore este e-mail.')
            ->salutation('Plataforma de Eventos');
    }
}
