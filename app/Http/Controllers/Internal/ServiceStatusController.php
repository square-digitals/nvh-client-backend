<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Service;
use App\Notifications\ServiceLiveNotification;
use App\Notifications\ServiceRejectedNotification;
use App\Notifications\ServiceSuspendedNotification;
use App\Notifications\ServiceTerminatedNotification;
use App\Notifications\ServiceUnsuspendedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ServiceStatusController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id'             => ['required', 'string'],
            'status'         => ['required', 'string', 'in:pending_approval,provisioning,active,suspended,failed,rejected,terminated'],
            'url'            => ['nullable', 'string'],
            'failed_reason'  => ['nullable', 'string'],
            'provisioned_at' => ['nullable', 'string'],
            'client_id'      => ['nullable', 'string'],
            'type'           => ['nullable', 'string'],
            'name'           => ['nullable', 'string'],
            'domain'         => ['nullable', 'string'],
        ]);

        Log::info('service-status push received', [
            'id'     => $data['id'],
            'status' => $data['status'],
        ]);

        $service = Service::find($data['id']);

        if (! $service) {
            // Restore from admin data so future pushes succeed.
            if (empty($data['client_id']) || empty($data['type']) || empty($data['name'])) {
                Log::warning('service-status push: service not found and payload lacks restore data', ['id' => $data['id']]);
                return response()->json(['message' => 'Service not found.'], 404);
            }

            $client = Client::find($data['client_id']);

            if (! $client) {
                Log::warning('service-status push: service not found, client not found either', ['id' => $data['id'], 'client_id' => $data['client_id']]);
                return response()->json(['message' => 'Service not found.'], 404);
            }

            $service = Service::create([
                'id'             => $data['id'],
                'client_id'      => $data['client_id'],
                'type'           => $data['type'],
                'name'           => $data['name'],
                'domain'         => $data['domain'] ?? null,
                'status'         => $data['status'],
                'url'            => $data['url'] ?? null,
                'failed_reason'  => $data['failed_reason'] ?? null,
                'provisioned_at' => isset($data['provisioned_at']) ? \Carbon\Carbon::parse($data['provisioned_at']) : null,
                'synced_at'      => now(),
            ]);

            Log::info('service-status push: restored missing service from admin data', ['id' => $data['id']]);
            return response()->json(['message' => 'Service status updated.']);
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
            $this->dispatchNotification($service, $oldStatus);
        }

        return response()->json(['message' => 'Service status updated.']);
    }

    private function dispatchNotification(Service $service, string $oldStatus): void
    {
        $client = $service->client;

        match ($service->status) {
            'active'             => $client->notify(
                                        $oldStatus === 'suspended'
                                            ? new ServiceUnsuspendedNotification($service)
                                            : new ServiceLiveNotification($service)
                                    ),
            'suspended'          => $client->notify(new ServiceSuspendedNotification($service)),
            'terminated'         => $client->notify(new ServiceTerminatedNotification($service)),
            'rejected', 'failed' => $client->notify(new ServiceRejectedNotification($service)),
            default              => null,
        };
    }
}
