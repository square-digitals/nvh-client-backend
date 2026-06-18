<?php

namespace Tests\Unit\Jobs;

use App\Jobs\PollServiceStatusJob;
use App\Models\Client;
use App\Models\Service;
use App\Services\AdminApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PollServiceStatusJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(string $status = 'pending_approval'): Service
    {
        return Service::factory()->create([
            'client_id' => Client::factory()->create()->id,
            'status'    => $status,
        ]);
    }

    private function mockApi(?array $response): AdminApiService
    {
        $mock = $this->createMock(AdminApiService::class);
        $mock->method('getServiceStatus')->willReturn($response);
        return $mock;
    }

    public function test_stops_if_service_not_found(): void
    {
        Queue::fake();

        $api = $this->mockApi(['status' => 'active', 'url' => null, 'failed_reason' => null, 'provisioned_at' => null]);
        (new PollServiceStatusJob('non-existent-id'))->handle($api);

        Queue::assertNothingPushed();
    }

    public function test_stops_if_service_already_terminal(): void
    {
        Queue::fake();

        $service = $this->makeService('active');
        $api     = $this->mockApi(null);

        (new PollServiceStatusJob($service->id))->handle($api);

        Queue::assertNothingPushed();
    }

    public function test_reschedules_when_admin_returns_null(): void
    {
        Queue::fake();

        $service = $this->makeService('pending_approval');
        $api     = $this->mockApi(null);

        (new PollServiceStatusJob($service->id))->handle($api);

        Queue::assertPushedOn('sync', PollServiceStatusJob::class);
    }

    public function test_updates_service_to_active_and_stops_polling(): void
    {
        Queue::fake();

        $service = $this->makeService('provisioning');
        $api     = $this->mockApi([
            'status'         => 'active',
            'url'            => 'https://myblog.com',
            'failed_reason'  => null,
            'provisioned_at' => '2026-06-18T12:00:00Z',
        ]);

        (new PollServiceStatusJob($service->id))->handle($api);

        $service->refresh();
        $this->assertEquals('active', $service->status);
        $this->assertEquals('https://myblog.com', $service->url);
        $this->assertNotNull($service->provisioned_at);
        $this->assertNotNull($service->synced_at);

        // Terminal state — no re-poll
        Queue::assertNothingPushed();
    }

    public function test_updates_service_to_rejected_and_stops_polling(): void
    {
        Queue::fake();

        $service = $this->makeService('pending_approval');
        $api     = $this->mockApi([
            'status'         => 'rejected',
            'url'            => null,
            'failed_reason'  => 'Domain already in use.',
            'provisioned_at' => null,
        ]);

        (new PollServiceStatusJob($service->id))->handle($api);

        $service->refresh();
        $this->assertEquals('rejected', $service->status);
        $this->assertEquals('Domain already in use.', $service->failed_reason);
        Queue::assertNothingPushed();
    }

    public function test_reschedules_when_still_provisioning(): void
    {
        Queue::fake();

        $service = $this->makeService('pending_approval');
        $api     = $this->mockApi([
            'status'         => 'provisioning',
            'url'            => null,
            'failed_reason'  => null,
            'provisioned_at' => null,
        ]);

        (new PollServiceStatusJob($service->id))->handle($api);

        $service->refresh();
        $this->assertEquals('provisioning', $service->status);
        Queue::assertPushedOn('sync', PollServiceStatusJob::class);
    }

    public function test_updates_synced_at_timestamp_on_every_poll(): void
    {
        Queue::fake();

        $service = $this->makeService('provisioning');
        $this->assertNull($service->synced_at);

        $api = $this->mockApi([
            'status'         => 'provisioning',
            'url'            => null,
            'failed_reason'  => null,
            'provisioned_at' => null,
        ]);

        (new PollServiceStatusJob($service->id))->handle($api);

        $this->assertNotNull($service->fresh()->synced_at);
    }
}
