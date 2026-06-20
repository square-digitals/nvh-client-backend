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
        $expected = hash_hmac('sha256', $request->getContent(), config('services.nvh_admin.secret'));

        if (! hash_equals($expected, (string) $request->header('X-Webhook-Signature', ''))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $data = $request->validate([
            'external_id'      => ['required', 'string'],
            'status'           => ['required', 'string', 'in:active,suspended'],
            'suspended_reason' => ['nullable', 'string'],
            'suspended_at'     => ['nullable', 'string'],
        ]);

        $client = Client::where('external_admin_id', $data['external_id'])->first()
            ?? Client::find($data['external_id']);

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
