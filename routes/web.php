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

// Login por token cifrado (e-mail de acesso — 1 clique). No domínio do frontend
// (proxied), com sessão web. Ver MagicLinkService::tokenLinkFor.
Route::get('/auth/magic', [\App\Http\Controllers\Api\Auth\MagicLinkController::class, 'token'])
    ->middleware('throttle:10,1')
    ->name('auth.magic.token');
