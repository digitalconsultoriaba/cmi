<?php

namespace App\Domain\Events\Models;

/** Origem do check-in por dia (spec 012). */
class CheckinOrigin
{
    public const QR = 'qr';
    public const MANUAL = 'manual';
    public const ADMIN_ADJUST = 'admin_adjust';

    public const ALL = [self::QR, self::MANUAL, self::ADMIN_ADJUST];
}
