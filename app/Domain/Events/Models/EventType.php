<?php

namespace App\Domain\Events\Models;

use App\Domain\Events\Models\Concerns\TracksAuditors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lookup com CRUD admin (spec 003) — por isso tem soft delete + auditoria,
 * diferente dos statuses.
 */
class EventType extends Model
{
    use SoftDeletes;
    use TracksAuditors;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];
}
