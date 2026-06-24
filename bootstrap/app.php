<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::get('/', fn () => response()->json(['message' => 'NVH Client Portal API']));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->prepend(\App\Http\Middleware\ForceJsonResponse::class);
        $middleware->prepend(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->appendToGroup('api', [
            \App\Http\Middleware\EncryptCookies::class,
        ]);

        $middleware->alias([
            'internal.secret' => \App\Http\Middleware\ValidateInternalSecret::class,
            'csrf'            => \App\Http\Middleware\VerifyCsrfCookie::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn () => true);

        $exceptions->render(function (\Throwable $e, $request) {
            if ((app()->isProduction() || app()->environment('staging')) && $request->is('api/*')) {
                $status = match (true) {
                    method_exists($e, 'getStatusCode') => $e->getStatusCode(),
                    property_exists($e, 'status') && is_int($e->status) => $e->status,
                    default => 500,
                };
                if ($status >= 500) {
                    \Illuminate\Support\Facades\Log::error($e->getMessage(), ['exception' => $e]);
                    return response()->json(['message' => 'An unexpected error occurred.'], 500);
                }
            }
        });
    })->create();
