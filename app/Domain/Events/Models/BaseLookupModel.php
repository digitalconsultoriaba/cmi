<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base dos lookups seeded (statuses, roles): sem soft delete/auditoria.
 */
abstract class BaseLookupModel extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function idFor(string $slug): int
    {
        return static::query()->where('slug', $slug)->value('id')
            ?? throw new \RuntimeException(static::class.": lookup '$slug' não seeded.");
    }

    public static function idsFor(array $slugs): array
    {
        return static::query()->whereIn('slug', $slugs)->pluck('id')->all();
    }
}
