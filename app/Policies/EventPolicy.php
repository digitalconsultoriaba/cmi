<?php

namespace App\Policies;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventStatus;
use App\Domain\Events\Models\Role;
use App\Models\User;

/**
 * Fundação do RBAC (contracts/rbac.md): admin gerencia o evento; evento não
 * publicado só é visível ao admin. Policies de tesouraria/portaria/inscrito
 * chegam nas specs 004/005/007.
 */
class EventPolicy
{
    public function view(?User $user, Event $event): bool
    {
        if ($event->status?->slug === EventStatus::PUBLISHED) {
            return true;
        }

        return $user?->hasRole(Role::ADMIN) ?? false;
    }

    public function update(User $user, Event $event): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    public function publish(User $user, Event $event): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    public function cancel(User $user, Event $event): bool
    {
        return $user->hasRole(Role::ADMIN);
    }
}
