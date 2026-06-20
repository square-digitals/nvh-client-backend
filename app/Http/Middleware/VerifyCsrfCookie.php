<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyCsrfCookie
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return $next($request);
        }

        $cookieToken = $request->cookie('XSRF-TOKEN');
        $headerToken = $request->header('X-XSRF-TOKEN');

        Log::debug('CSRF check', [
            'cookie' => $cookieToken ? substr($cookieToken, 0, 10).'...' : 'MISSING',
            'header' => $headerToken ? substr($headerToken, 0, 10).'...' : 'MISSING',
            'match'  => $cookieToken && $headerToken ? hash_equals($cookieToken, $headerToken) : false,
        ]);

        if (! $cookieToken || ! $headerToken || ! hash_equals($cookieToken, $headerToken)) {
            Log::warning('CSRF token mismatch', [
                'method' => $request->method(),
                'path'   => $request->path(),
                'ip'     => $request->ip(),
            ]);
            return response()->json(['message' => 'CSRF token mismatch.'], 419);
        }

        return $next($request);
    }
}
