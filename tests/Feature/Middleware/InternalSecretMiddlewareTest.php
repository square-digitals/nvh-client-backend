<?php

namespace Tests\Feature\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InternalSecretMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register a test-only internal route
        Route::middleware('internal.secret')
            ->post('/_test/internal', fn () => response()->json(['ok' => true]));
    }

    public function test_request_without_secret_is_rejected(): void
    {
        $this->postJson('/_test/internal')->assertUnauthorized();
    }

    public function test_request_with_wrong_secret_is_rejected(): void
    {
        $this->withHeader('X-Internal-Secret', 'wrong-secret')
            ->postJson('/_test/internal')
            ->assertUnauthorized();
    }

    public function test_secret_passed_as_query_param_is_rejected(): void
    {
        config(['services.internal.secret' => 'real-secret']);

        $this->postJson('/_test/internal?secret=real-secret')->assertUnauthorized();
    }

    public function test_request_with_correct_secret_passes(): void
    {
        config(['services.internal.secret' => 'real-secret']);

        $this->withHeader('X-Internal-Secret', 'real-secret')
            ->postJson('/_test/internal')
            ->assertOk();
    }
}
