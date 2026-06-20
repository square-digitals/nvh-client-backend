<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        $client = Client::findOrFail($id);

        if (! hash_equals(sha1($client->email), $hash)) {
            return response()->json(['message' => 'Invalid verification link.'], 422);
        }

        if (! $request->hasValidSignature()) {
            return response()->json(['message' => 'Verification link has expired.'], 410);
        }

        if ($client->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 422);
        }

        $client->markEmailAsVerified();
        event(new Verified($client));

        return response()->json(['message' => 'Email verified successfully.']);
    }

    public function resend(Request $request): \Illuminate\Http\JsonResponse
    {
        $client = $request->user();

        if ($client->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 422);
        }

        $client->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent.']);
    }
}
