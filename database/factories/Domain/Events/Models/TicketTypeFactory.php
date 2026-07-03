<?php

namespace Database\Factories\Domain\Events\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketTypeFactory extends Factory
{
    protected $model = TicketType::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => 'Ingresso '.fake()->unique()->word(),
            'price' => '150.00',
            'seats_per_ticket' => 1,
            'audience' => 'any',
            'is_active' => true,
        ];
    }
}
