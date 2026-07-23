<?php

namespace App\Models;

use App\Domain\Events\Models\Role;
use App\Notifications\ResetPasswordPtBr;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'document',
        'phone',
        'google_id',
        'avatar_url',
        'potencia',
        'loja',
        'grau',
        'cargo_loja',
        'cargo_potencia',
        'endereco',
        'cidade',
        'pais',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    /** E-mail sempre normalizado (trim + minúsculas) — edge case da spec 002. */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_strtolower(trim($value)),
        );
    }

    // ── Notificações pt-BR (spec 002) ───────────────────────────────

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordPtBr($token));
    }

    // ── RBAC (specs/001-fundacao/contracts/rbac.md) ─────────────────

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    /** Ao menos um dos papéis (semântica do middleware require.role). */
    public function hasAnyRole(array $slugs): bool
    {
        return $this->roles->whereIn('slug', $slugs)->isNotEmpty();
    }

    /** Atribui um papel sem duplicar (cadastro/Google → attendee). */
    public function assignRole(string $slug): void
    {
        $this->roles()->syncWithoutDetaching([Role::idFor($slug)]);
        $this->unsetRelation('roles');
    }
}
