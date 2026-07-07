<?php

namespace App\Domain\Events\Services;

use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Acesso passwordless por URL assinada (spec 014). Mesma mecânica do
 * verify-email (middleware `signed`): integridade + expiração; sem senha.
 */
class MagicLinkService
{
    /** Dias de validade do link mágico. */
    public const TTL_DAYS = 14;

    /** URL assinada de login para o usuário. */
    public function linkFor(User $user): string
    {
        return URL::temporarySignedRoute(
            'auth.magic',
            now()->addDays(self::TTL_DAYS),
            ['user' => $user->id],
        );
    }
}
