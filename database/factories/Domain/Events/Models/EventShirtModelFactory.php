<?php

namespace Database\Factories\Domain\Events\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventShirtModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventShirtModelFactory extends Factory
{
    protected $model = EventShirtModel::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'label' => 'Modelo '.fake()->unique()->word(),
            'is_active' => true,
        ];
    }
}
