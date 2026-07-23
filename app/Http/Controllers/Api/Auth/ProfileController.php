<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Autoatendimento da conta (spec 009): dados, senha e foto do próprio usuário.
 */
class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'document' => ['nullable', 'string', 'max:30'],
            'potencia' => ['nullable', 'string', 'max:255'],
            'loja' => ['nullable', 'string', 'max:255'],
            'grau' => ['nullable', 'in:aprendiz,companheiro,mestre,mestre_instalado'],
            'cargo_loja' => ['nullable', 'string', 'max:255'],
            'cargo_potencia' => ['nullable', 'string', 'max:255'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'pais' => ['nullable', 'string', 'max:120'],
        ], [], ['name' => 'nome']);

        $request->user()->forceFill($data)->save();

        return UserResource::make($request->user()->fresh());
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['nullable', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [], ['password' => 'nova senha']);

        $user = $request->user();

        // Quem já tem senha confirma a atual (contas só-Google não). No 1º acesso
        // obrigatório (must_change_password) dispensa — acabou de logar com a temporária.
        if (! $user->must_change_password
            && $user->password !== null
            && ! Hash::check($data['current_password'] ?? '', $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'A senha atual está incorreta.',
            ]);
        }

        // Trocar a senha limpa a exigência de troca no 1º acesso (spec 002/015).
        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();

        return UserResource::make($user->fresh());
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
        ], [], ['avatar' => 'foto']);

        $user = $request->user();
        $old = $user->avatar_url;

        $path = $request->file('avatar')->store('avatars', 'public');
        // Caminho RELATIVO (/storage/…) — independente de origem/porta, funciona
        // no dev (via proxy Vite) e em produção.
        $user->forceFill(['avatar_url' => '/storage/'.$path])->save();

        if ($old) {
            Storage::disk('public')->delete(ltrim(str_replace('/storage/', '', $old), '/'));
        }

        return UserResource::make($user->fresh());
    }
}
