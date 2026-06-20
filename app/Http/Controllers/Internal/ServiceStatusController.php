<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Notifications\ServiceLiveNotification;
use App\Notifications\ServiceRejectedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceStatusController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'external_id'    => ['required', 'string'],
            'status'         => ['required', 'string', 'in:pending_approval,provisioning,active,suspended,failed,rejected,terminated'],
            'url'            => ['nullable', 'string'],
            'failed_reason'  => ['nullable', 'string'],
            'provisioned_at' => ['nullable', 'string'],
        ]);

        $service = Service::find($data['external_id']);

        if (! $service) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        $oldStatus = $service->status;

        $service->update([
            'status'         => $data['status'],
            'url'            => $data['url'] ?? null,
            'failed_reason'  => $data['failed_reason'] ?? null,
            'provisioned_at' => isset($data['provisioned_at'])
                                    ? \Carbon\Carbon::parse($data['provisioned_at'])
                                    : null,
            'synced_at'      => now(),
        ]);

        if ($oldStatus !== $service->status) {
            $this->dispatchNotification($service);
        }

        return response()->json(['message' => 'Service status updated.']);
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
