<?php

namespace Tests\Feature\Admin;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Role;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketStatus;
use App\Domain\Events\Models\TicketType;
use App\Models\User;
use Tests\TestCase;

abstract class AdminTestCase extends TestCase
{
    protected function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::ADMIN);

        return $user;
    }

    protected function attendee(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::ATTENDEE);

        return $user;
    }

    /** Cria um ticket "vivo" (paid) para exercitar as guardas de venda. */
    protected function sellTicket(Event $event, TicketType $type, array $attrs = []): Ticket
    {
        $order = Order::factory()->create(['event_id' => $event->id]);

        return Ticket::factory()->create([
            'order_id' => $order->id,
            'event_id' => $event->id,
            'ticket_type_id' => $type->id,
            'status_id' => TicketStatus::idFor(TicketStatus::PAID),
            ...$attrs,
        ]);
    }
}
