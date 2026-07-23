<?php

namespace App\Http\Controllers\Api\Auth;

use App\Domain\Events\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::query()->create($request->only(['name', 'email', 'password']));
            $user->assignRole(Role::ATTENDEE);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return UserResource::make($user)->response()->setStatusCode(201);
    }
}
