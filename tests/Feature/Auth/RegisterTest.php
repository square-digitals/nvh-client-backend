<?php

namespace Tests\Feature\Auth;

use App\Jobs\SyncClientToAdminJob;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_register(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Alice Johnson',
            'email'                 => 'alice@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['client' => ['id', 'name', 'email', 'status']]);

        $this->assertDatabaseHas('clients', ['email' => 'alice@example.com']);
    }

    public function test_registration_dispatches_sync_job_on_sync_queue(): void
    {
        Queue::fake();

        $this->postJson('/api/auth/register', [
            'name'                  => 'Bob Smith',
            'email'                 => 'bob@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        Queue::assertPushedOn('sync', SyncClientToAdminJob::class);
    }

    public function test_registration_sets_auth_and_csrf_cookies(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Carol White',
            'email'                 => 'carol@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCookie('nvh_client_token')
            ->assertCookie('XSRF-TOKEN');
    }

    public function test_registration_requires_unique_email(): void
    {
        Queue::fake();

        Client::factory()->create(['email' => 'existing@example.com']);

        $this->postJson('/api/auth/register', [
            'name'                  => 'Duplicate',
            'email'                 => 'existing@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $this->postJson('/api/auth/register', [
            'name'     => 'Bad User',
            'email'    => 'bad@example.com',
            'password' => 'password123',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['password']);
    }
}
