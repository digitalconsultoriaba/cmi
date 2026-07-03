<?php

namespace App\Http\Controllers\Api\Auth;

use App\Domain\Events\Models\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect(): JsonResponse
    {
        $url = Socialite::driver('google')->redirect()->getTargetUrl();

        return ApiResponse::data(['url' => $url]);
    }

    /**
     * Três vias (research, Decisão 5): login por google_id → vínculo por e-mail
     * normalizado → criação (verificada, sem senha, papel attendee).
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect(config('app.frontend_url').'/entrar?google=erro');
        }

        $user = DB::transaction(function () use ($googleUser) {
            $existing = User::query()->where('google_id', $googleUser->getId())->first();

            if ($existing !== null) {
                return $existing; // via 1: já vinculado — e-mail pode até ter mudado no Google
            }

            $email = mb_strtolower(trim($googleUser->getEmail()));
            $byEmail = User::query()->where('email', $email)->first();

            if ($byEmail !== null) {
                // via 2: vincular sem duplicar; não toca senha nem papéis
                $byEmail->forceFill([
                    'google_id' => $googleUser->getId(),
                    'avatar_url' => $byEmail->avatar_url ?? $googleUser->getAvatar(),
                ])->save();

                return $byEmail;
            }

            // via 3: conta nova — sem senha, verificada (e-mail atestado pelo Google)
            $user = new User([
                'name' => $googleUser->getName() ?: $email,
                'email' => $email,
                'google_id' => $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar(),
            ]);
            $user->email_verified_at = now();
            $user->save();
            $user->assignRole(Role::ATTENDEE);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect(config('app.frontend_url').'/entrar?google=ok');
    }
}
