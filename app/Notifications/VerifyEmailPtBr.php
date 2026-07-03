<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailPtBr extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Confirme seu e-mail')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Confirme seu endereço de e-mail para concluir seu cadastro na Plataforma de Eventos.')
            ->action('Confirmar e-mail', $url)
            ->line('Se você não criou esta conta, ignore esta mensagem.')
            ->salutation('Plataforma de Eventos');
    }
}
