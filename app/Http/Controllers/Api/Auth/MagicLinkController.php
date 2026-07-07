<?php

namespace App\Http\Controllers\Api\Auth;

use App\Domain\Events\Services\MagicLinkService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Acesso passwordless (spec 014). O link (URL assinada, middleware `signed`)
 * autentica a sessão Sanctum e redireciona ao SPA. Sem senha trafegando.
 */
class MagicLinkController extends Controller
{
    /** Consome o link mágico → login por sessão + redirect. */
    public function consume(Request $request, int $user): RedirectResponse
    {
        $account = User::query()->findOrFail($user);

        Auth::guard('web')->login($account);
        $request->session()->regenerate();

        if (! $account->hasVerifiedEmail()) {
            $account->markEmailAsVerified();
        }

        return redirect(config('app.frontend_url').'/minha-conta/ingressos');
    }

    /** Solicita um novo link por e-mail (resposta neutra + throttle). */
    public function request(Request $request, MagicLinkService $magic): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $account = User::query()->where('email', mb_strtolower(trim($data['email'])))->first();

        if ($account !== null) {
            try {
                $account->notify(new \App\Notifications\MagicLinkPtBr($magic->linkFor($account)));
            } catch (\Throwable) {
                // silencioso — resposta neutra
            }
        }

        return ApiResponse::data(['sent' => true]);
    }
}
