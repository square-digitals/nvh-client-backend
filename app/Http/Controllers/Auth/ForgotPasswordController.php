<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::broker('clients')->sendResetLink($request->only('email'));

        // Always return success to prevent email enumeration
        return response()->json(['message' => 'If that email is registered, a reset link has been sent.']);
    }
}
