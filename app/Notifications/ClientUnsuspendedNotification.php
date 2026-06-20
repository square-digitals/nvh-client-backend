<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientUnsuspendedNotification extends Notification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your account has been reactivated')
            ->markdown('emails.client-unsuspended', [
                'name' => $notifiable->name,
            ]);
    }
}
