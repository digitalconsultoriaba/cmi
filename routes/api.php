<?php

use App\Http\Controllers\Api\Admin\CourtesyVoucherController;
use App\Http\Controllers\Api\Admin\EventController as AdminEventController;
use App\Http\Controllers\Api\Admin\EventTypeController;
use App\Http\Controllers\Api\Admin\LandingBlockController;
use App\Http\Controllers\Api\Admin\ShirtModelController;
use App\Http\Controllers\Api\Admin\ShirtSizeController;
use App\Http\Controllers\Api\Admin\SponsorshipController;
use App\Http\Controllers\Api\Admin\TicketLotController;
use App\Http\Controllers\Api\Admin\TicketTypeController;
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

// ── Painel administrativo (spec 003) ────────────────────────────────
Route::prefix('admin')->middleware(['auth:sanctum', 'require.role:admin'])->scopeBindings()->group(function () {
    Route::get('/events', [AdminEventController::class, 'index']);
    Route::get('/events/{event}', [AdminEventController::class, 'show']);
    Route::put('/events/{event}', [AdminEventController::class, 'update']);
    Route::post('/events/{event}/publish', [AdminEventController::class, 'publish']);
    Route::post('/events/{event}/cancel', [AdminEventController::class, 'cancel']);
    Route::post('/events/{event}/banner', [AdminEventController::class, 'banner']);

    Route::apiResource('event-types', EventTypeController::class)->except(['show']);

    Route::prefix('events/{event}')->group(function () {
        Route::get('/ticket-types', [TicketTypeController::class, 'index']);
        Route::post('/ticket-types', [TicketTypeController::class, 'store']);
        Route::patch('/ticket-types/reorder', [TicketTypeController::class, 'reorder']);
        Route::put('/ticket-types/{ticketType}', [TicketTypeController::class, 'update']);
        Route::delete('/ticket-types/{ticketType}', [TicketTypeController::class, 'destroy']);

        Route::get('/lots', [TicketLotController::class, 'index']);
        Route::post('/lots', [TicketLotController::class, 'store']);
        Route::patch('/lots/reorder', [TicketLotController::class, 'reorder']);
        Route::put('/lots/{ticketLot}', [TicketLotController::class, 'update']);
        Route::delete('/lots/{ticketLot}', [TicketLotController::class, 'destroy']);

        Route::get('/shirt-models', [ShirtModelController::class, 'index']);
        Route::post('/shirt-models', [ShirtModelController::class, 'store']);
        Route::put('/shirt-models/{shirtModel}', [ShirtModelController::class, 'update']);
        Route::delete('/shirt-models/{shirtModel}', [ShirtModelController::class, 'destroy']);
        Route::post('/shirt-models/{shirtModel}/sizes', [ShirtSizeController::class, 'store']);
        Route::put('/shirt-models/{shirtModel}/sizes/{size}', [ShirtSizeController::class, 'update']);
        Route::delete('/shirt-models/{shirtModel}/sizes/{size}', [ShirtSizeController::class, 'destroy']);

        Route::get('/landing-blocks', [LandingBlockController::class, 'index']);
        Route::post('/landing-blocks', [LandingBlockController::class, 'store']);
        Route::patch('/landing-blocks/reorder', [LandingBlockController::class, 'reorder']);
        Route::put('/landing-blocks/{landingBlock}', [LandingBlockController::class, 'update']);
        Route::delete('/landing-blocks/{landingBlock}', [LandingBlockController::class, 'destroy']);

        Route::get('/courtesy-vouchers', [CourtesyVoucherController::class, 'index']);
        Route::post('/courtesy-vouchers', [CourtesyVoucherController::class, 'generate']);
        Route::patch('/courtesy-vouchers/{courtesyVoucher}/distribute', [CourtesyVoucherController::class, 'distribute']);

        Route::get('/sponsorships', [SponsorshipController::class, 'index']);
        Route::post('/sponsorships', [SponsorshipController::class, 'store']);
        Route::put('/sponsorships/{sponsorship}', [SponsorshipController::class, 'update']);
        Route::post('/sponsorships/{sponsorship}/cancel', [SponsorshipController::class, 'cancel']);
        Route::post('/sponsorships/{sponsorship}/installments/{number}/pay', [SponsorshipController::class, 'payInstallment']);
    });
});
