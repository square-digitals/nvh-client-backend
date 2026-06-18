<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        if (! $this->validSignature($request)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event      = $request->input('event');
        $externalId = $request->input('external_id');

        $client = Client::find($externalId);

        match ($event) {
            'client.suspended'   => $client?->update([
                'status'           => 'suspended',
                'suspended_reason' => $request->input('suspended_reason'),
            ]),
            'client.unsuspended' => $client?->update([
                'status'           => 'active',
                'suspended_reason' => null,
            ]),
            default => null,
        };

        return response()->json(['message' => 'ok']);
    }

    private function validSignature(Request $request): bool
    {
        $secret   = config('services.nvh_admin.secret');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, (string) $request->header('X-Webhook-Signature', ''));
    }
}
