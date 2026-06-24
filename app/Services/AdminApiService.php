<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Service;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class AdminApiService
{
    private GuzzleClient $http;

    public function __construct(?GuzzleClient $http = null)
    {
        $this->http = $http ?? new GuzzleClient([
            'base_uri' => config('services.nvh_admin.base_url'),
            'headers'  => [
                'X-Internal-Secret' => config('services.nvh_admin.secret'),
                'Accept'            => 'application/json',
                'Content-Type'      => 'application/json',
            ],
            'timeout' => 10,
        ]);
    }

    public function syncClient(Client $client): void
    {
        try {
            $this->http->post('/api/internal/clients/sync', [
                'json' => ['clients' => [[
                    'id'        => $client->id,
                    'name'      => $client->name,
                    'email'     => $client->email,
                    'plan_slug' => $client->plan,
                    'status'    => $client->status,
                ]]],
            ]);
        } catch (GuzzleException $e) {
            Log::error('AdminApiService::syncClient failed', [
                'client_id' => $client->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    public function syncService(Service $service): void
    {
        try {
            $this->http->post('/api/internal/services/sync', [
                'json' => ['services' => [[
                    'id'        => $service->id,
                    'client_id' => $service->client_id,
                    'type'      => $service->type,
                    'status'    => $service->status,
                    'name'      => $service->name,
                    'domain'    => $service->domain,
                ]]],
            ]);
        } catch (GuzzleException $e) {
            Log::error('AdminApiService::syncService failed', [
                'service_id' => $service->id,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function getServiceStatus(Service $service): ?array
    {
        try {
            $response = $this->http->get("/api/internal/services/{$service->id}");
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('AdminApiService::getServiceStatus failed', [
                'service_id' => $service->id,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function syncInvoicePaid(Invoice $invoice): void
    {
        try {
            $this->http->post('/api/internal/invoices/sync', [
                'json' => [[
                    'id'        => $invoice->id,
                    'client_id' => $invoice->client_id,
                    'status'    => 'paid',
                    'paid_at'   => now()->toIso8601String(),
                ]],
            ]);
        } catch (GuzzleException $e) {
            Log::error('AdminApiService::syncInvoicePaid failed', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
