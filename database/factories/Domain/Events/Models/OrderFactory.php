<?php

namespace Database\Factories\Domain\Events\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'buyer_user_id' => User::factory(),
            'buyer_name' => fake()->name(),
            'buyer_email' => fake()->unique()->safeEmail(),
            'total_amount' => '0.00',
            'status_id' => fn () => OrderStatus::idFor(OrderStatus::PENDING),
            'reserved_until' => now()->addMinutes(30),
        ];
    }
}
