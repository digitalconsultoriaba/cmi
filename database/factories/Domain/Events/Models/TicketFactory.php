<?php

namespace Database\Factories\Domain\Events\Models;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketStatus;
use App\Domain\Events\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'event_id' => fn (array $attrs) => Order::find($attrs['order_id'])->event_id,
            'ticket_type_id' => fn (array $attrs) => TicketType::factory()
                ->create(['event_id' => Order::find($attrs['order_id'])->event_id])->id,
            'participant_name' => fake()->name(),
            'participant_email' => fake()->safeEmail(),
            'unit_price' => '150.00', // snapshot
            'status_id' => fn () => TicketStatus::idFor(TicketStatus::RESERVED),
        ];
    }

    public function status(string $slug): static
    {
        return $this->state(fn () => ['status_id' => TicketStatus::idFor($slug)]);
    }
}
