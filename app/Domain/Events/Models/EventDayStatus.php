<?php

namespace App\Domain\Events\Models;

/**
 * Situação operacional do dia do evento (spec 012) — DERIVADA (nunca coluna):
 * finished > blocked > in_progress (há check-ins) > open.
 */
class EventDayStatus
{
    public const OPEN = 'open';
    public const IN_PROGRESS = 'in_progress';
    public const FINISHED = 'finished';
    public const BLOCKED = 'blocked';

    public const ALL = [self::OPEN, self::IN_PROGRESS, self::FINISHED, self::BLOCKED];
}
