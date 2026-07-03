<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Link do e-mail (assinado — middleware `signed` garante integridade).
     * Não exige sessão: o clique pode vir de outro navegador. Redireciona ao front.
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        // Hash do e-mail confere? (parte do padrão MustVerifyEmail)
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        // Revisita do link é inócua — mesma resposta de sucesso.
        return redirect(config('app.frontend_url').'/entrar?verified=1');
    }

    /** Reenvio (autenticado, throttle auth-email — FR-004). */
    public function resend(Request $request): JsonResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return ApiResponse::data(null);
    }
}
