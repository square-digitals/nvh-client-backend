<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class WelcomeNotification extends VerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to New Ventures Hosting — Verify Your Email')
            ->markdown('emails.welcome', [
                'url'  => $url,
                'name' => $this->notifiable->name,
            ]);
    }
}
