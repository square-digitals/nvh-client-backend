<?php

namespace App\Notifications;

use App\Models\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientSuspendedNotification extends Notification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(public readonly Client $client) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your account has been suspended')
            ->markdown('emails.client-suspended', [
                'name'   => $notifiable->name,
                'reason' => $this->client->suspended_reason,
            ]);
    }
}
