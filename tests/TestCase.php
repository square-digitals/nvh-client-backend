<?php

namespace Tests;

use App\Http\Middleware\VerifyCsrfCookie;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CSRF is tested in isolation in CsrfMiddlewareTest.
        // All other feature tests bypass it so they focus on business logic.
        $this->withoutMiddleware(VerifyCsrfCookie::class);
    }
}
