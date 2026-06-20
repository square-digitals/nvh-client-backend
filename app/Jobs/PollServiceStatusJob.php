<?php

namespace App\Jobs;

use App\Models\Service;
use App\Notifications\ServiceLiveNotification;
use App\Notifications\ServiceRejectedNotification;
use App\Services\AdminApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PollServiceStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 30;

    private const POLLING_STATES  = ['pending_approval', 'provisioning'];
    private const POLL_DELAY_SECS = 120;

    public function __construct(public readonly string $serviceId) {}

    public function handle(AdminApiService $adminApi): void
    {
        $service = Service::find($this->serviceId);

        if (! $service || ! in_array($service->status, self::POLLING_STATES, true)) {
            return;
        }

        $data = $adminApi->getServiceStatus($service);

        if (! $data) {
            $this->reschedule();
            return;
        }

        $oldStatus = $service->status;

        $service->update([
            'status'           => $data['status'],
            'url'              => $data['url'] ?? null,
            'failed_reason'    => $data['failed_reason'] ?? null,
            'provisioned_at'   => isset($data['provisioned_at'])
                                    ? \Carbon\Carbon::parse($data['provisioned_at'])
                                    : null,
            'admin_service_id' => $data['admin_service_id'] ?? $service->admin_service_id,
            'synced_at'        => now(),
        ]);

        if ($oldStatus !== $service->status) {
            $this->dispatchNotification($service);
        }

        if (in_array($service->status, self::POLLING_STATES, true)) {
            $this->reschedule();
        }
    }

    private function reschedule(): void
    {
        static::dispatch($this->serviceId)
            ->delay(now()->addSeconds(self::POLL_DELAY_SECS))
            ->onQueue('sync');
    }

    private function dispatchNotification(Service $service): void
    {
        $client = $service->client;

        match ($service->status) {
            'active'             => $client->notify(new ServiceLiveNotification($service)),
            'rejected', 'failed' => $client->notify(new ServiceRejectedNotification($service)),
            default              => null,
        };
    }
}
