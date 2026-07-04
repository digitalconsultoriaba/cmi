<?php

namespace App\Domain\Events\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialAttachment extends Model
{
    protected $guarded = [];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(FinancialEntry::class, 'entry_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
