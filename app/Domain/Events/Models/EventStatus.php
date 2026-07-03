<?php

namespace App\Domain\Events\Models;

class EventStatus extends BaseLookupModel
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const CANCELLED = 'cancelled';
    public const FINISHED = 'finished';

    public const ALL = [self::DRAFT, self::PUBLISHED, self::CANCELLED, self::FINISHED];

    /** Situações terminais: rejeitam transição (409). */
    public const TERMINAL = [self::CANCELLED, self::FINISHED];
}
