<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Notifications\ClientSuspendedNotification;
use App\Notifications\ClientUnsuspendedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientStatusController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id'               => ['required', 'string'],
            'status'           => ['required', 'string', 'in:active,suspended'],
            'suspended_reason' => ['nullable', 'string'],
            'suspended_at'     => ['nullable', 'string'],
        ]);

        $client = Client::find($data['id']);

        if (! $client) {
            return response()->json(['message' => 'Client not found.'], 404);
        }

        $oldStatus = $client->status;

        if ($data['status'] === 'suspended') {
            $client->update([
                'status'           => 'suspended',
                'suspended_reason' => $data['suspended_reason'] ?? null,
            ]);
            $client->tokens()->delete();
        } else {
            $client->update([
                'status'           => 'active',
                'suspended_reason' => null,
            ]);
        }

        if ($oldStatus !== $client->status) {
            match ($client->status) {
                'suspended' => $client->notify(new ClientSuspendedNotification($client)),
                'active'    => $client->notify(new ClientUnsuspendedNotification($client)),
            };
        }

        return response()->json(['message' => 'Client status updated.']);
    }
}
