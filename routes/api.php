<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Internal\ServiceStatusController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public auth routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('register',        [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('login',           [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('forgot-password', [ForgotPasswordController::class, 'send'])->middleware('throttle:5,1');
    Route::post('reset-password',  [ResetPasswordController::class, 'reset'])->middleware('throttle:5,1');

    Route::middleware(['auth:sanctum', 'csrf'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);
    });
});

/*
|--------------------------------------------------------------------------
| Authenticated client routes (Steps 6–12 added here)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'csrf', 'verified'])->group(function () {
    // Services
    Route::get('services',              [ServiceController::class, 'index']);
    Route::post('services',             [ServiceController::class, 'store']);
    Route::get('services/{service}',    [ServiceController::class, 'show']);
    Route::delete('services/{service}', [ServiceController::class, 'terminate']);

    // Account, Invoices, Tickets — wired in subsequent steps
});

/*
|--------------------------------------------------------------------------
| Internal — inbound from admin backend (Steps 8, 10)
|--------------------------------------------------------------------------
*/
Route::prefix('internal')->middleware('internal.secret')->group(function () {
    Route::post('service-status', [ServiceStatusController::class, 'update']);
    // InvoiceSyncController — wired in Step 10
});

/*
|--------------------------------------------------------------------------
| Payment webhooks — signature validated inside controller (Step 11)
|--------------------------------------------------------------------------
*/
// Route::post('webhooks/paystack', [PaystackWebhookController::class, 'handle']);
