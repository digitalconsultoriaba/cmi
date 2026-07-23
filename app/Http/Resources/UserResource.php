<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape `me` do contrato (specs/002-auth-inscrito/contracts/auth-api.md).
 * O wrapper `data` vem do JsonResource — igual ao envelope padrão.
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'emailVerified' => $this->email_verified_at !== null,
            'document' => $this->document,
            'phone' => $this->phone,
            'potencia' => $this->potencia,
            'loja' => $this->loja,
            'grau' => $this->grau,
            'cargoLoja' => $this->cargo_loja,
            'cargoPotencia' => $this->cargo_potencia,
            'endereco' => $this->endereco,
            'cidade' => $this->cidade,
            'pais' => $this->pais,
            'avatarUrl' => $this->avatar_url,
            'hasPassword' => $this->password !== null,
            'mustChangePassword' => (bool) $this->must_change_password,
            'roles' => $this->roles->pluck('slug')->values(),
        ];
    }
}
