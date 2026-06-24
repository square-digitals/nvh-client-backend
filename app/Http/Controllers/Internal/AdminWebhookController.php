<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        if (! $this->validSignature($request)) {
            Log::warning('AdminWebhook: invalid signature', ['payload' => $request->all()]);
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event      = $request->input('event');
        $externalId = $request->input('id');

        Log::info('AdminWebhook received', ['event' => $event, 'id' => $externalId]);

        $client = Client::find($externalId);

        if (! $client) {
            Log::warning('AdminWebhook: client not found', ['id' => $externalId]);
        }

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
