<?php

namespace Database\Factories\Domain\Events\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\TicketLot;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketLotFactory extends Factory
{
    protected $model = TicketLot::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'ticket_type_id' => null, // global por padrão
            'name' => fake()->unique()->numerify('Lote ##'),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(10),
            'sort' => 0,
            'is_active' => true,
        ];
    }
}
