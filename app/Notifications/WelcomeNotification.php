<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class WelcomeNotification extends VerifyEmail implements ShouldQueue
{
    public string $queue = 'notifications';

    protected function verificationUrl($notifiable): string
    {
        $backendUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
        );

        parse_str(parse_url($backendUrl, PHP_URL_QUERY), $query);

        return config('app.frontend_url') . '/verify-email?' . http_build_query([
            'id'        => $notifiable->getKey(),
            'hash'      => sha1($notifiable->getEmailForVerification()),
            'expires'   => $query['expires'],
            'signature' => $query['signature'],
        ]);
    }

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
