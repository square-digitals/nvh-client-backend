<?php

namespace App\Notifications;

use App\Models\Service;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceUnsuspendedNotification extends Notification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(public readonly Service $service) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your service has been reactivated — ' . $this->service->name)
            ->markdown('emails.service-unsuspended', [
                'name'    => $notifiable->name,
                'service' => $this->service,
            ]);
    }
}
