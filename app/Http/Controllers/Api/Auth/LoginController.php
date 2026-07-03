<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function login(LoginRequest $request): JsonResource
    {
        $throttleKey = 'auth-login:'.$request->email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw new ThrottleRequestsException;
        }

        // Conta só-Google: orientar em vez de erro opaco (FR-010).
        $user = User::query()->where('email', $request->email)->first();

        if ($user !== null && $user->password === null && $user->google_id !== null) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'Esta conta usa o Google para entrar. Use "Entrar com Google" '
                    .'ou defina uma senha em "Esqueci minha senha".',
            ]);
        }

        if (! Auth::attempt($request->only(['email', 'password']))) {
            RateLimiter::hit($throttleKey, 60);

            // Mensagem genérica — não revela qual campo errou (FR-005).
            throw ValidationException::withMessages([
                'email' => 'Credenciais inválidas.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return UserResource::make(Auth::user());
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::data(null);
    }
}
