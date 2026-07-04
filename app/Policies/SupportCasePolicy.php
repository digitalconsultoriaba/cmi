<?php

namespace App\Policies;

use App\Domain\Events\Models\SupportCase;
use App\Models\User;

class SupportCasePolicy
{
    /** Inscrito só vê os próprios casos (SC-006 da spec 006). */
    public function view(User $user, SupportCase $case): bool
    {
        return $case->user_id === $user->id;
    }
}
