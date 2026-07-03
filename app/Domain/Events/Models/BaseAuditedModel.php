<?php

namespace App\Domain\Events\Models;

use App\Domain\Events\Models\Concerns\TracksAuditors;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Base com auditoria mas SEM soft delete — registros financeiros/notas imutáveis
 * (payments, sponsorship_installments, support_case_notes): correção = novo registro.
 */
abstract class BaseAuditedModel extends Model
{
    use HasFactory;
    use TracksAuditors;

    protected $guarded = [];
}
