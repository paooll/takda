<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => Notification::TYPE_TURN_APPROACHING,
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(),
            'data' => [],
            'read_at' => null,
        ];
    }
}
