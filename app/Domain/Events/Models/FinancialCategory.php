<?php

namespace App\Domain\Events\Models;

class FinancialCategory extends BaseModel
{
    protected $casts = ['is_active' => 'boolean'];

    public const INCOME = 'income';
    public const EXPENSE = 'expense';

    public function entries()
    {
        return $this->hasMany(FinancialEntry::class, 'category_id');
    }
}
