<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\VerifyCsrfCookie;
use Illuminate\Http\Request;
use Tests\TestCase;

class CsrfMiddlewareTest extends TestCase
{
    private function handle(Request $request): int
    {
        return (new VerifyCsrfCookie())
            ->handle($request, fn ($req) => response()->json(['ok' => true]))
            ->getStatusCode();
    }

    private function makePost(array $cookies = [], array $headers = []): int
    {
        $request = Request::create('/_test', 'POST');
        foreach ($cookies as $k => $v) {
            $request->cookies->set($k, $v);
        }
        foreach ($headers as $k => $v) {
            $request->headers->set($k, $v);
        }
        return $this->handle($request);
    }

    public function test_get_requests_are_exempt(): void
    {
        $request = Request::create('/_test', 'GET');
        $this->assertEquals(200, $this->handle($request));
    }

    public function test_post_without_csrf_cookie_returns_419(): void
    {
        $this->assertEquals(419, $this->makePost());
    }

    public function test_post_without_csrf_header_returns_419(): void
    {
        $this->assertEquals(419, $this->makePost(cookies: ['XSRF-TOKEN' => 'token']));
    }

    public function test_post_with_mismatched_tokens_returns_419(): void
    {
        $this->assertEquals(419, $this->makePost(
            cookies: ['XSRF-TOKEN' => 'correct'],
            headers: ['X-XSRF-TOKEN' => 'wrong'],
        ));
    }

    public function test_post_with_matching_tokens_passes(): void
    {
        $this->assertEquals(200, $this->makePost(
            cookies: ['XSRF-TOKEN' => 'abc123'],
            headers: ['X-XSRF-TOKEN' => 'abc123'],
        ));
    }
}
