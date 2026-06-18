<?php

use App\Jobs\PollServiceStatusJob;
use App\Models\Service;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Safety-net: re-queue polling for any services stuck in transitional states.
// The job self-reschedules, so this only fires for services that slipped through
// (e.g. worker was down when the service was created).
Schedule::call(function () {
    Service::whereIn('status', ['pending_approval', 'provisioning'])
        ->whereNull('synced_at')
        ->orWhere('synced_at', '<', now()->subMinutes(5))
        ->whereIn('status', ['pending_approval', 'provisioning'])
        ->each(fn (Service $service) => PollServiceStatusJob::dispatch($service->id)->onQueue('sync'));
})->everyTwoMinutes()->name('poll-service-status')->withoutOverlapping();
