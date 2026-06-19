<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $client = Client::findOrFail($id);

        if (! hash_equals(sha1($client->email), $hash)) {
            return redirect(config('app.frontend_url') . '/verify-email?error=invalid');
        }

        if (! $request->hasValidSignature()) {
            return redirect(config('app.frontend_url') . '/verify-email?error=expired');
        }

        if ($client->hasVerifiedEmail()) {
            return redirect(config('app.frontend_url') . '/dashboard?verified=already');
        }

        $client->markEmailAsVerified();
        event(new Verified($client));

        return redirect(config('app.frontend_url') . '/dashboard?verified=1');
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
