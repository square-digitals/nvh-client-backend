<?php

namespace App\Http\Controllers;

use App\Http\Requests\Service\StoreServiceRequest;
use App\Jobs\PollServiceStatusJob;
use App\Jobs\SyncServiceToAdminJob;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $services = $request->user()->services()
            ->latest()
            ->get();

        return response()->json(['services' => $services]);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = $request->user()->services()->create([
            'type'   => $request->input('type', 'wordpress'),
            'name'   => $request->input('name'),
            'domain' => $request->input('domain'),
            'status' => 'pending_approval',
        ]);

        SyncServiceToAdminJob::dispatch($service)->onQueue('sync');
        PollServiceStatusJob::dispatch($service->id)
            ->delay(now()->addSeconds(120))
            ->onQueue('sync');

        return response()->json(['service' => $service], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $service = $request->user()->services()->findOrFail($id);

        return response()->json(['service' => $service]);
    }

    public function terminate(Request $request, string $id): JsonResponse
    {
        $service = $request->user()->services()
            ->whereNotIn('status', ['terminated', 'rejected'])
            ->findOrFail($id);

        $service->update(['status' => 'terminated']);

        SyncServiceToAdminJob::dispatch($service)->onQueue('sync');

        return response()->json(['message' => 'Service terminated.']);
    }
}
