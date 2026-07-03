<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordPtBr extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = config('app.frontend_url')
            .'/redefinir-senha?token='.$this->token
            .'&email='.urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('Redefinição de senha')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Recebemos um pedido de redefinição de senha para a sua conta.')
            ->action('Definir nova senha', $url)
            ->line('Este link expira em '.config('auth.passwords.users.expire').' minutos e só pode ser usado uma vez.')
            ->line('Se você não pediu a redefinição, ignore esta mensagem.')
            ->salutation('Plataforma de Eventos');
    }
}
