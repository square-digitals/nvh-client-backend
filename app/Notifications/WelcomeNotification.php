<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class WelcomeNotification extends VerifyEmail
{
    public function toMail(mixed $notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Welcome to New Ventures Hosting — Verify Your Email')
            ->markdown('emails.welcome', [
                'url'  => $url,
                'name' => $notifiable->name,
            ]);
    }
}
