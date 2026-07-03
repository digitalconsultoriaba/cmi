<?php

namespace App\Domain\Events\Support;

use Illuminate\Support\Str;

/**
 * Código público único e não sequencial (FR-006) — nunca expor id em URL/QR.
 * O model define `public const CODE_PREFIX = 'XXX';`.
 */
trait HasPublicCode
{
    public static function bootHasPublicCode(): void
    {
        static::creating(function ($model) {
            $model->code ??= static::generatePublicCode();
        });
    }

    public static function generatePublicCode(): string
    {
        do {
            $code = static::CODE_PREFIX.'-'.strtoupper(Str::random(10));
        } while (static::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
