<?php

namespace Database\Factories\Domain\Events\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventStatus;
use App\Domain\Events\Models\EventType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $name = 'Evento '.fake()->unique()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'event_type_id' => fn () => EventType::query()->first()?->id
                ?? EventType::query()->create(['name' => 'Seminário'])->id,
            'starts_at' => now()->addDays(30),
            'ends_at' => now()->addDays(32),
            'location' => fake()->city(),
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addDays(25),
            'pricing_mode' => 'paid',
            'status_id' => fn () => EventStatus::idFor(EventStatus::DRAFT),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status_id' => EventStatus::idFor(EventStatus::PUBLISHED),
        ]);
    }
}
