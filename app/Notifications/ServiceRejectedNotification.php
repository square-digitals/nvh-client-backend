<?php

namespace App\Notifications;

use App\Models\Service;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceRejectedNotification extends Notification implements ShouldQueue
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
            ->subject('Service request update — ' . $this->service->name)
            ->markdown('emails.service-rejected', [
                'name'    => $notifiable->name,
                'service' => $this->service,
            ]);
    }
}
