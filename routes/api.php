<?php

use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Api\Auth\GoogleController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\MeController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('auth')->group(function () {
    // Público
    Route::post('/register', RegisterController::class);
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])
        ->middleware('throttle:auth-forgot');
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);

    // Link assinado do e-mail (sem sessão — pode abrir em outro navegador)
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Google (Socialite)
    Route::get('/google/redirect', [GoogleController::class, 'redirect']);
    Route::get('/google/callback', [GoogleController::class, 'callback']);

    // Autenticado
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', MeController::class);
        Route::post('/logout', [LoginController::class, 'logout']);
        Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:auth-email');
    });
});
