<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro bruto de notificação externa — auditoria e dedupe
 * (unique provider+external_id). Sem soft delete/auditoria: é imutável.
 */
class WebhookEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
