<?php

namespace App\Domain\Events\Models\Concerns;

/**
 * Auditoria automática de created_by/updated_by (constituição, princípio V).
 */
trait TracksAuditors
{
    public static function bootTracksAuditors(): void
    {
        static::creating(function ($model) {
            $model->created_by ??= auth()->id();
            $model->updated_by ??= auth()->id();
        });

        static::updating(function ($model) {
            $model->updated_by = auth()->id() ?? $model->updated_by;
        });
    }
}
