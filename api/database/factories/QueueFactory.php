<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Queue>
 */
class QueueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->randomElement(['Cashier', 'Information Desk', 'Payments']),
            'code_prefix' => strtoupper(fake()->unique()->randomLetter()),
            'avg_service_minutes' => 10,
            'is_active' => true,
            'last_issued_number' => 0,
        ];
    }
}
