<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateInternalSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        // Reject secret via query string — it would be logged in plaintext by proxies
        if ($request->query('secret') || $request->query('X-Internal-Secret')) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $secret = config('services.nvh_admin.secret');

        if (! $secret || $request->header('X-Internal-Secret') !== $secret) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
