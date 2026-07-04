<?php

namespace Tests\Feature\Finance;

use App\Domain\Events\Models\FinancialCategory;
use App\Domain\Events\Models\FinancialPaymentMethod;
use App\Domain\Events\Models\Role;
use App\Models\User;
use Tests\Feature\Lifecycle\LifecycleTestCase;

abstract class FinanceTestCase extends LifecycleTestCase
{
    protected function finance(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::TREASURY);

        return $user;
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::ADMIN);

        return $user;
    }

    protected function anyCategory(string $direction = 'expense'): int
    {
        return FinancialCategory::query()->where('direction', $direction)->value('id');
    }

    protected function anyMethod(): int
    {
        return FinancialPaymentMethod::query()->value('id');
    }

    /** Payload base de um lançamento. */
    protected function entryPayload(array $overrides = []): array
    {
        return array_merge([
            'direction' => 'payable',
            'description' => 'Despesa teste',
            'amount' => '1000.00',
            'category_id' => $this->anyCategory('expense'),
            'due_date' => now()->addDays(10)->toDateString(),
        ], $overrides);
    }
}
