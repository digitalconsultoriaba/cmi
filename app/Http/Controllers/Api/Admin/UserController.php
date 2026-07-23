<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Events\Models\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestão de usuários da equipe (spec 009) — o admin cria contas de financeiro
 * (tesouraria) e recepção (portaria/QR) com e-mail + senha na hora.
 * Papéis operacionais apenas: attendee é o papel do inscrito comum.
 */
class UserController extends Controller
{
    private const TEAM_ROLES = [Role::ADMIN, Role::TREASURY, Role::GATE];

    public function index()
    {
        $users = User::query()
            ->with('roles')
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', self::TEAM_ROLES))
            ->orderBy('name')
            ->get();

        return ApiResponse::data($users->map(fn (User $u) => $this->present($u))->all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(self::TEAM_ROLES)],
        ], [], ['name' => 'nome', 'email' => 'e-mail', 'password' => 'senha', 'role' => 'papel']);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'], // hash pelo cast do model
        ]);
        $user->roles()->sync([Role::idFor($data['role'])]);

        return ApiResponse::data($this->present($user->fresh('roles')), 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'role' => ['sometimes', 'required', Rule::in(self::TEAM_ROLES)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
        ], [], ['name' => 'nome', 'role' => 'papel', 'password' => 'senha']);

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        if (isset($data['role'])) {
            $user->roles()->sync([Role::idFor($data['role'])]);
        }

        return ApiResponse::data($this->present($user->fresh('roles')));
    }

    public function destroy(Request $request, User $user)
    {
        // Não pode remover a própria conta (evita se trancar para fora)
        if ($user->id === $request->user()->id) {
            return ApiResponse::error('Você não pode remover a própria conta.', 'self_delete', 409);
        }

        $user->roles()->detach();
        $user->delete();

        return ApiResponse::data(null);
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('slug')->all(),
        ];
    }
}
