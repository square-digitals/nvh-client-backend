<?php

namespace Tests\Feature\Services;

use App\Jobs\PollServiceStatusJob;
use App\Jobs\SyncServiceToAdminJob;
use App\Models\Client;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsVerifiedClient(): Client
    {
        $client = Client::factory()->create();
        Sanctum::actingAs($client, guard: 'sanctum');
        return $client;
    }

    // --- index ---

    public function test_client_can_list_their_services(): void
    {
        $client = $this->actingAsVerifiedClient();
        Service::factory()->count(3)->create(['client_id' => $client->id]);
        Service::factory()->create(); // another client's service

        $this->getJson('/api/services')
            ->assertOk()
            ->assertJsonCount(3, 'services');
    }

    public function test_guest_cannot_list_services(): void
    {
        $this->getJson('/api/services')->assertUnauthorized();
    }

    // --- store ---

    public function test_client_can_request_a_wordpress_service(): void
    {
        Queue::fake();
        $this->actingAsVerifiedClient();

        $this->postJson('/api/services', [
            'name'   => 'My Blog',
            'domain' => 'myblog.com',
        ])->assertCreated()
          ->assertJsonPath('service.status', 'pending_approval')
          ->assertJsonPath('service.type', 'wordpress')
          ->assertJsonPath('service.domain', 'myblog.com');
    }

    public function test_service_request_dispatches_sync_and_poll_jobs(): void
    {
        Queue::fake();
        $this->actingAsVerifiedClient();

        $this->postJson('/api/services', [
            'name'   => 'My Blog',
            'domain' => 'myblog.com',
        ]);

        Queue::assertPushedOn('sync', SyncServiceToAdminJob::class);
        Queue::assertPushedOn('sync', PollServiceStatusJob::class);
    }

    public function test_store_rejects_private_ip_domain(): void
    {
        $this->actingAsVerifiedClient();

        $this->postJson('/api/services', [
            'name'   => 'Bad Service',
            'domain' => '192.168.1.1',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['domain']);
    }

    public function test_store_rejects_localhost_domain(): void
    {
        $this->actingAsVerifiedClient();

        $this->postJson('/api/services', [
            'name'   => 'Bad Service',
            'domain' => 'localhost',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['domain']);
    }

    public function test_store_rejects_internal_tld(): void
    {
        $this->actingAsVerifiedClient();

        $this->postJson('/api/services', [
            'name'   => 'Bad Service',
            'domain' => 'mysite.local',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['domain']);
    }

    public function test_store_requires_name_and_domain(): void
    {
        $this->actingAsVerifiedClient();

        $this->postJson('/api/services', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'domain']);
    }

    // --- show ---

    public function test_client_can_view_their_service(): void
    {
        $client  = $this->actingAsVerifiedClient();
        $service = Service::factory()->create(['client_id' => $client->id]);

        $this->getJson("/api/services/{$service->id}")
            ->assertOk()
            ->assertJsonPath('service.id', $service->id);
    }

    public function test_client_cannot_view_another_clients_service(): void
    {
        $this->actingAsVerifiedClient();
        $other = Service::factory()->create(); // different client

        $this->getJson("/api/services/{$other->id}")->assertNotFound();
    }

    // --- terminate ---

    public function test_client_can_terminate_their_service(): void
    {
        Queue::fake();
        $client  = $this->actingAsVerifiedClient();
        $service = Service::factory()->active()->create(['client_id' => $client->id]);

        $this->deleteJson("/api/services/{$service->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Service terminated.');

        $this->assertDatabaseHas('services', ['id' => $service->id, 'status' => 'terminated']);
        Queue::assertPushedOn('sync', SyncServiceToAdminJob::class);
    }

    public function test_client_cannot_terminate_another_clients_service(): void
    {
        $this->actingAsVerifiedClient();
        $other = Service::factory()->active()->create();

        $this->deleteJson("/api/services/{$other->id}")->assertNotFound();
    }

    public function test_cannot_terminate_already_terminated_service(): void
    {
        $client  = $this->actingAsVerifiedClient();
        $service = Service::factory()->terminated()->create(['client_id' => $client->id]);

        $this->deleteJson("/api/services/{$service->id}")->assertNotFound();
    }
}
