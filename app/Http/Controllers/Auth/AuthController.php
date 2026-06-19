<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SyncClientToAdminJob;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:clients,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $client = Client::create($data);
        $client->refresh();

        $client->sendEmailVerificationNotification();

        SyncClientToAdminJob::dispatch($client)->onQueue('sync');

        $token = $client->createToken('client-token', ['*'], now()->addMinutes(60))->plainTextToken;

        return response()->json(['client' => $client->only([
            'id', 'name', 'email', 'status', 'plan', 'email_verified_at',
        ])], 201)->withCookie($this->authCookie($token))
                        ->withCookie($this->csrfCookie());
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $client = Client::where('email', $data['email'])->first();

        if (! $client || ! Hash::check($data['password'], $client->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if ($client->status === 'suspended') {
            return response()->json([
                'message' => 'Your account has been suspended.' . ($client->suspended_reason ? ' Reason: ' . $client->suspended_reason : ''),
            ], 403);
        }

        $client->tokens()->delete();
        $token = $client->createToken('client-token', ['*'], now()->addMinutes(60))->plainTextToken;

        return response()->json(['client' => $client->only([
            'id', 'name', 'email', 'status', 'plan', 'email_verified_at',
        ])])->withCookie($this->authCookie($token))
            ->withCookie($this->csrfCookie());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.'])
            ->withCookie($this->expiredCookie('nvh_client_token'))
            ->withCookie($this->expiredCookie('XSRF-TOKEN'));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['client' => $request->user()->only([
            'id', 'name', 'email', 'phone', 'company', 'status', 'plan', 'email_verified_at',
        ])]);
    }

    private function authCookie(string $token): \Symfony\Component\HttpFoundation\Cookie
    {
        $secure   = ! app()->environment('local');
        $sameSite = app()->environment('staging') ? 'None' : 'Lax';

        return cookie(
            name: 'nvh_client_token', value: $token, minutes: 60,
            path: '/', domain: config('session.domain'),
            secure: $secure, httpOnly: true, sameSite: $sameSite,
        );
    }

    private function csrfCookie(): \Symfony\Component\HttpFoundation\Cookie
    {
        $secure   = ! app()->environment('local');
        $sameSite = app()->environment('staging') ? 'None' : 'Lax';

        return cookie(
            name: 'XSRF-TOKEN', value: \Illuminate\Support\Str::random(40), minutes: 60,
            path: '/', domain: config('session.domain'),
            secure: $secure, httpOnly: false, sameSite: $sameSite,
        );
    }

    private function expiredCookie(string $name): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(name: $name, value: '', minutes: -1, path: '/', domain: config('session.domain'));
    }
}
