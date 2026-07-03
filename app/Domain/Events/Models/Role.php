<?php

namespace App\Domain\Events\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends BaseLookupModel
{
    public const ADMIN = 'admin';
    public const TREASURY = 'treasury';
    public const GATE = 'gate';
    public const ATTENDEE = 'attendee';

    public const ALL = [self::ADMIN, self::TREASURY, self::GATE, self::ATTENDEE];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
