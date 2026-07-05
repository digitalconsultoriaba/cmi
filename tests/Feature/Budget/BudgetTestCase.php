<?php

namespace Tests\Feature\Budget;

use App\Domain\Events\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Lifecycle\LifecycleTestCase;

abstract class BudgetTestCase extends LifecycleTestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::ADMIN);

        return $user;
    }

    protected function treasury(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::TREASURY);

        return $user;
    }

    protected function attendee(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::ATTENDEE);

        return $user;
    }

    protected function budgetUrl(string $suffix = ''): string
    {
        return "/api/admin/events/{$this->event->id}/budget{$suffix}";
    }
}
