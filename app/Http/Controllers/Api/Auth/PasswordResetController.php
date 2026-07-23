<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    /**
     * Resposta idêntica exista ou não a conta (FR-011) — nunca revela cadastro.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return ApiResponse::data(null);
    }

    /**
     * Token de uso único/expirável do broker. Define senha inclusive para
     * conta só-Google (passa a ter os dois métodos de entrada).
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only(['email', 'password', 'password_confirmation', 'token']),
            function ($user, string $password) {
                // Redefinir também zera a exigência de troca no 1º acesso.
                $user->forceFill(['password' => $password, 'must_change_password' => false])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'Link inválido ou expirado. Solicite uma nova redefinição.',
            ]);
        }

        return ApiResponse::data(null);
    }
}
