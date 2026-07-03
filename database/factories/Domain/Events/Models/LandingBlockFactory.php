<?php

namespace Database\Factories\Domain\Events\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\LandingBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

class LandingBlockFactory extends Factory
{
    protected $model = LandingBlock::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'type' => fake()->randomElement(LandingBlock::TYPES),
            'sort' => 0,
            'is_active' => true,
            'payload' => ['title' => fake()->sentence(3)],
        ];
    }
}
