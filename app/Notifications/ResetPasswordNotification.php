<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    public function toMail(mixed $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Passwort zurücksetzen')
            ->greeting('Hallo!')
            ->line('Sie erhalten diese E-Mail, weil wir eine Anfrage zum Zurücksetzen des Passworts für Ihr Konto erhalten haben.')
            ->action('Passwort zurücksetzen', $url)
            ->line('Dieser Link ist für ' . config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60) . ' Minuten gültig.')
            ->line('Falls Sie kein neues Passwort angefordert haben, können Sie diese E-Mail ignorieren.')
            ->salutation('Mit freundlichen Grüßen, ' . config('app.name'));
    }
}
