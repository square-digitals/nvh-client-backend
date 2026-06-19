<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TriggerSyncController extends Controller
{
    public function sync(): JsonResponse
    {
        $adminUrl = config('services.nvh_admin.base_url');
        $secret   = config('services.nvh_admin.secret');

        $this->syncClients($adminUrl, $secret);
        $this->syncServices($adminUrl, $secret);

        return response()->json(['message' => 'ok']);
    }

    private function syncClients(string $adminUrl, string $secret): void
    {
        $clients = Client::all()->map(fn (Client $client) => [
            'external_id' => $client->id,
            'name'        => $client->name,
            'email'       => $client->email,
            'status'      => $client->status,
            'plan_slug'   => $client->plan,
        ])->values()->all();

        try {
            Http::withHeaders(['X-Internal-Secret' => $secret])
                ->post("{$adminUrl}/api/internal/clients/sync", ['clients' => $clients]);
        } catch (\Throwable $e) {
            Log::error('TriggerSync: clients sync failed', ['error' => $e->getMessage()]);
        }
    }

    private function syncServices(string $adminUrl, string $secret): void
    {
        $services = Service::all()->map(fn (Service $service) => [
            'external_id'        => $service->id,
            'client_external_id' => $service->client_id,
            'type'               => $service->type,
            'name'               => $service->name,
            'domain'             => $service->domain,
            'status'             => $service->status,
        ])->values()->all();

        try {
            Http::withHeaders(['X-Internal-Secret' => $secret])
                ->post("{$adminUrl}/api/internal/services/sync", ['services' => $services]);
        } catch (\Throwable $e) {
            Log::error('TriggerSync: services sync failed', ['error' => $e->getMessage()]);
        }
    }
}
