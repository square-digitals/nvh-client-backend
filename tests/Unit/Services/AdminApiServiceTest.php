<?php

namespace Tests\Unit\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Service;
use App\Services\AdminApiService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AdminApiServiceTest extends TestCase
{
    private function makeService(array $responses, array &$history = []): AdminApiService
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        return new AdminApiService(new GuzzleClient(['handler' => $handler]));
    }

    public function test_sync_client_posts_correct_payload(): void
    {
        $history = [];
        $service = $this->makeService([new Response(200)], $history);

        $client = new Client([
            'name'   => 'Alice',
            'email'  => 'alice@example.com',
            'plan'   => 'starter',
            'status' => 'active',
        ]);
        $client->id = 'uuid-001';

        $service->syncClient($client);

        $this->assertCount(1, $history);
        $body = json_decode($history[0]['request']->getBody()->getContents(), true);

        $this->assertEquals([[
            'id'        => 'uuid-001',
            'name'      => 'Alice',
            'email'     => 'alice@example.com',
            'plan_slug' => 'starter',
            'status'    => 'active',
        ]], $body['clients']);
    }

    public function test_sync_service_posts_correct_payload(): void
    {
        $history = [];
        $adminService = $this->makeService([new Response(200)], $history);

        $service            = new Service();
        $service->id        = 'svc-001';
        $service->client_id = 'client-001';
        $service->type      = 'wordpress';
        $service->status    = 'pending_approval';
        $service->name      = 'My Blog';
        $service->domain    = 'myblog.com';

        $adminService->syncService($service);

        $this->assertCount(1, $history);
        $body = json_decode($history[0]['request']->getBody()->getContents(), true);

        $this->assertEquals([[
            'id'        => 'svc-001',
            'client_id' => 'client-001',
            'type'      => 'wordpress',
            'status'    => 'pending_approval',
            'name'      => 'My Blog',
            'domain'    => 'myblog.com',
        ]], $body['services']);
    }

    public function test_sync_invoice_paid_posts_correct_payload(): void
    {
        $history = [];
        $adminService = $this->makeService([new Response(200)], $history);

        $invoice             = new Invoice();
        $invoice->id         = 'inv-001';
        $invoice->client_id  = 'client-001';

        $adminService->syncInvoicePaid($invoice);

        $this->assertCount(1, $history);
        $body = json_decode($history[0]['request']->getBody()->getContents(), true);

        $this->assertEquals('inv-001', $body[0]['id']);
        $this->assertEquals('paid', $body[0]['status']);
        $this->assertArrayHasKey('paid_at', $body[0]);
    }

    public function test_sync_client_does_not_throw_on_network_failure(): void
    {
        Log::shouldReceive('error')->once()->withArgs(function ($message, $context) {
            return $message === 'AdminApiService::syncClient failed'
                && isset($context['client_id'], $context['error']);
        });

        $mock    = new MockHandler([new ConnectException('Connection refused', new Request('POST', '/'))]);
        $handler = HandlerStack::create($mock);
        $service = new AdminApiService(new GuzzleClient(['handler' => $handler]));

        $client     = new Client(['name' => 'Bob', 'email' => 'bob@example.com']);
        $client->id = 'uuid-002';

        $service->syncClient($client);

        $this->assertTrue(true);
    }

    public function test_sync_service_does_not_throw_on_network_failure(): void
    {
        Log::shouldReceive('error')->once()->withArgs(fn ($msg) => $msg === 'AdminApiService::syncService failed');

        $mock    = new MockHandler([new ConnectException('Connection refused', new Request('POST', '/'))]);
        $handler = HandlerStack::create($mock);
        $service = new AdminApiService(new GuzzleClient(['handler' => $handler]));

        $svc = new Service();
        $svc->id = 'svc-fail';

        $service->syncService($svc);

        $this->assertTrue(true);
    }
}
