<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Jobs\TriggerFullSyncJob;
use Illuminate\Http\JsonResponse;

class TriggerSyncController extends Controller
{
    public function sync(): JsonResponse
    {
        TriggerFullSyncJob::dispatch()->onQueue('sync');

        return response()->json(['message' => 'ok']);
    }
}
