<?php

namespace Database\Factories\Domain\Events\Models;

use App\Domain\Events\Models\EventShirtModel;
use App\Domain\Events\Models\EventShirtSize;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventShirtSizeFactory extends Factory
{
    protected $model = EventShirtSize::class;

    public function definition(): array
    {
        return [
            'shirt_model_id' => EventShirtModel::factory(),
            'event_id' => fn (array $attrs) => EventShirtModel::find($attrs['shirt_model_id'])->event_id,
            'label' => strtoupper(fake()->unique()->lexify('??')),
            'stock_quantity' => null, // ilimitado por padrão
            'is_active' => true,
        ];
    }
}
