<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\Role;
use App\Models\User;

/**
 * Resolve/cria contas (comprador e participantes) a partir do e-mail no
 * checkout guest (spec 014). Contas nascem sem senha (acesso por magic link),
 * papel attendee. E-mail já existente é reutilizado (não duplica).
 */
class GuestBuyerService
{
    /** Comprador do pedido (obrigatório nome + e-mail). */
    public function resolveBuyer(string $name, string $email): User
    {
        return $this->resolve($email, $name);
    }

    /** Conta do participante a partir do e-mail (nome de apoio). */
    public function resolveParticipant(string $email, string $name): User
    {
        return $this->resolve($email, $name);
    }

    private function resolve(string $email, string $name): User
    {
        $email = mb_strtolower(trim($email));

        $user = User::query()->where('email', $email)->first();
        if ($user !== null) {
            return $user;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => null, // sem senha — acesso por magic link
        ]);
        $user->assignRole(Role::ATTENDEE);

        return $user;
    }
}
