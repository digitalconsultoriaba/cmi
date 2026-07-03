<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Envelopes padrão da API (specs/001-fundacao/contracts/api-conventions.md).
 * Sucesso: { data } com chaves camelCase. Erro: { message, type, status, errors? }.
 */
class ApiResponse
{
    public static function data(mixed $payload, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $payload], $status);
    }

    public static function error(string $message, string $type, int $status, ?array $errors = null): JsonResponse
    {
        $body = ['message' => $message, 'type' => $type, 'status' => $status];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }
}
