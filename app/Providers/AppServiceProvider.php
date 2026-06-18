<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Sanctum::getAccessTokenFromRequestUsing(function ($request) {
            return $request->cookie('nvh_client_token') ?: $request->bearerToken();
        });
    }
}
