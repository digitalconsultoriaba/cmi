<?php

namespace App\Domain\Events\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

/**
 * Acesso passwordless (spec 014). Duas formas:
 * - assinada (`linkFor`): URL assinada no domínio do backend (uso interno).
 * - token (`tokenLinkFor`): link no domínio do FRONTEND com token cifrado (não
 *   depende de assinatura de URL → funciona atrás de proxy/túnel, mesmo domínio
 *   do SPA, então o cookie de sessão fica no lugar certo). Usado no e-mail de
 *   acesso para login em 1 clique.
 */
class MagicLinkService
{
    /** Dias de validade do link mágico. */
    public const TTL_DAYS = 14;

    /** URL assinada de login para o usuário (rota web assinada no backend). */
    public function linkFor(User $user): string
    {
        return URL::temporarySignedRoute(
            'auth.magic',
            now()->addDays(self::TTL_DAYS),
            ['user' => $user->id],
        );
    }

    /** Link de login por token cifrado, no domínio do frontend (1 clique). */
    public function tokenLinkFor(User $user): string
    {
        $token = Crypt::encryptString(json_encode([
            'user' => $user->id,
            'exp' => now()->addDays(self::TTL_DAYS)->getTimestamp(),
        ]));

        return rtrim((string) config('app.frontend_url'), '/').'/auth/magic?t='.urlencode($token);
    }
}
