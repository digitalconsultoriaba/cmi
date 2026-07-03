<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResource
    {
        return UserResource::make($request->user());
    }
}
