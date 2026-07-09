<?php

namespace App\Domain\Events\Models;

use App\Domain\Events\Models\Concerns\TracksAuditors;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base das tabelas de negócio: soft delete + auditoria (constituição, princípio V).
 * A fronteira de entrada é FormRequest (specs 002+) com whitelist via validated();
 * ainda assim guardamos colunas de sistema como cinto extra (nunca vêm de input).
 */
abstract class BaseModel extends Model
{
    use HasFactory;
    use SoftDeletes;
    use TracksAuditors;

    protected $guarded = ['id', 'created_by', 'updated_by', 'deleted_at'];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
