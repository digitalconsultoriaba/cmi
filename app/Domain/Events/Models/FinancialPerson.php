<?php

namespace App\Domain\Events\Models;

class FinancialPerson extends BaseModel
{
    protected $casts = ['is_active' => 'boolean'];

    public const KINDS = ['supplier', 'customer', 'sponsor', 'participant', 'provider', 'other'];

    public function entries()
    {
        return $this->hasMany(FinancialEntry::class, 'person_id');
    }
}
