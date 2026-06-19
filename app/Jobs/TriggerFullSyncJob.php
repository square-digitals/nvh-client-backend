<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TriggerFullSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 10;

    public function handle(): void
    {
        $adminUrl = config('services.nvh_admin.base_url');
        $secret   = config('services.nvh_admin.secret');

        $this->syncClients($adminUrl, $secret);
        $this->syncServices($adminUrl, $secret);
    }

    private function syncClients(string $adminUrl, string $secret): void
    {
        $clients = Client::all()->map(fn (Client $c) => [
            'external_id' => $c->id,
            'name'        => $c->name,
            'email'       => $c->email,
            'status'      => $c->status,
            'plan_slug'   => $c->plan,
        ])->values()->all();

        try {
            Http::withHeaders(['X-Internal-Secret' => $secret])
                ->post("{$adminUrl}/api/internal/clients/sync", ['clients' => $clients]);
        } catch (\Throwable $e) {
            Log::error('TriggerFullSync: clients sync failed', ['error' => $e->getMessage()]);
        }
    }

    private function syncServices(string $adminUrl, string $secret): void
    {
        $services = Service::all()->map(fn (Service $s) => [
            'external_id'        => $s->id,
            'client_external_id' => $s->client_id,
            'type'               => $s->type,
            'name'               => $s->name,
            'domain'             => $s->domain,
            'status'             => $s->status,
        ])->values()->all();

        try {
            Http::withHeaders(['X-Internal-Secret' => $secret])
                ->post("{$adminUrl}/api/internal/services/sync", ['services' => $services]);
        } catch (\Throwable $e) {
            Log::error('TriggerFullSync: services sync failed', ['error' => $e->getMessage()]);
        }
    }
}
