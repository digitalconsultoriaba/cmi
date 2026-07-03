<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `require.role:admin,treasury` — exige ao menos UM dos papéis listados
 * (specs/001-fundacao/contracts/rbac.md). Não revela quais papéis a rota exige.
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('Não autenticado.', 'unauthenticated', 401);
        }

        if (! $user->hasAnyRole($roles)) {
            return ApiResponse::error('Você não tem permissão para esta ação.', 'forbidden', 403);
        }

        return $next($request);
    }
}
