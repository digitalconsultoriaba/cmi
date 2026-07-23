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
use Illuminate\Support\Facades\Crypt;

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

    /**
     * Consome o link do e-mail de acesso: token cifrado (?t=) → login por sessão
     * + redirect ao SPA. Sem assinatura de URL (funciona atrás de proxy/túnel);
     * no domínio do frontend, então o cookie de sessão fica no lugar certo. No
     * 1º acesso, a guarda do SPA leva à troca de senha já autenticado.
     */
    public function token(Request $request): RedirectResponse
    {
        $front = rtrim((string) config('app.frontend_url'), '/');

        try {
            $data = json_decode(Crypt::decryptString((string) $request->query('t')), true);
        } catch (\Throwable) {
            return redirect($front.'/entrar');
        }

        if (! is_array($data) || ! isset($data['user'], $data['exp']) || $data['exp'] < time()) {
            return redirect($front.'/entrar');
        }

        $account = User::find($data['user']);
        if ($account === null) {
            return redirect($front.'/entrar');
        }

        Auth::guard('web')->login($account);
        $request->session()->regenerate();

        if (! $account->hasVerifiedEmail()) {
            $account->markEmailAsVerified();
        }

        return redirect($front.'/minha-conta/ingressos');
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
