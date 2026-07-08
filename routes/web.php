<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Magic link passwordless (spec 014) — rota web (com sessão) para logar por URL
// assinada e redirecionar ao SPA. Fora do /api porque precisa de sessão.
Route::get('/auth/magic/{user}', [\App\Http\Controllers\Api\Auth\MagicLinkController::class, 'consume'])
    ->middleware(['signed', 'throttle:10,1'])
    ->name('auth.magic');
