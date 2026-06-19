<?php

namespace Tests\Feature\Internal;

use App\Models\Client;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceStatusWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-internal-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.internal.secret' => $this->secret]);
    }

    private function webhook(array $payload, ?string $secret = null): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-Internal-Secret', $secret ?? $this->secret)
            ->postJson('/api/internal/service-status', $payload);
    }

    private function makeService(string $status = 'provisioning'): Service
    {
        return Service::factory()->create([
            'client_id' => Client::factory()->create()->id,
            'status'    => $status,
        ]);
    }

    // --- Auth ---

    public function test_rejects_request_without_secret(): void
    {
        $this->postJson('/api/internal/service-status', [])->assertUnauthorized();
    }

    public function test_rejects_request_with_wrong_secret(): void
    {
        $this->webhook([], 'wrong-secret')->assertUnauthorized();
    }

    // --- Validation ---

    public function test_rejects_missing_required_fields(): void
    {
        $this->webhook([])->assertUnprocessable()
            ->assertJsonValidationErrors(['external_id', 'status']);
    }

    public function test_rejects_invalid_status_value(): void
    {
        $service = $this->makeService();

        $this->webhook([
            'external_id' => $service->id,
            'status'      => 'unknown-status',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['status']);
    }

    // --- Not found ---

    public function test_returns_404_for_unknown_service(): void
    {
        $this->webhook([
            'external_id' => 'non-existent-uuid',
            'status'      => 'active',
        ])->assertNotFound();
    }

    // --- Status updates ---

    public function test_updates_service_to_active(): void
    {
        $service = $this->makeService('provisioning');

        $this->webhook([
            'external_id'    => $service->id,
            'status'         => 'active',
            'url'            => 'https://myblog.com',
            'failed_reason'  => null,
            'provisioned_at' => '2026-06-18T12:00:00Z',
        ])->assertOk()->assertJsonPath('message', 'Service status updated.');

        $service->refresh();
        $this->assertEquals('active', $service->status);
        $this->assertEquals('https://myblog.com', $service->url);
        $this->assertNotNull($service->provisioned_at);
        $this->assertNotNull($service->synced_at);
    }

    public function test_updates_service_to_rejected_with_reason(): void
    {
        $service = $this->makeService('pending_approval');

        $this->webhook([
            'external_id'   => $service->id,
            'status'        => 'rejected',
            'failed_reason' => 'Domain already in use.',
        ])->assertOk();

        $service->refresh();
        $this->assertEquals('rejected', $service->status);
        $this->assertEquals('Domain already in use.', $service->failed_reason);
    }

    public function test_updates_service_to_provisioning(): void
    {
        $service = $this->makeService('pending_approval');

        $this->webhook([
            'external_id' => $service->id,
            'status'      => 'provisioning',
        ])->assertOk();

        $this->assertEquals('provisioning', $service->fresh()->status);
    }

    public function test_clears_url_and_reason_when_null(): void
    {
        $service = $this->makeService('active');
        $service->update(['url' => 'https://old.com', 'failed_reason' => 'old error']);

        $this->webhook([
            'external_id'   => $service->id,
            'status'        => 'suspended',
            'url'           => null,
            'failed_reason' => null,
        ])->assertOk();

        $service->refresh();
        $this->assertNull($service->url);
        $this->assertNull($service->failed_reason);
    }

    public function test_synced_at_is_stamped_on_update(): void
    {
        $service = $this->makeService();
        $this->assertNull($service->synced_at);

        $this->webhook([
            'external_id' => $service->id,
            'status'      => 'active',
        ])->assertOk();

        $this->assertNotNull($service->fresh()->synced_at);
    }
}
