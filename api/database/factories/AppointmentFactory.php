<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => User::factory(),
            'reference' => strtoupper(Str::random(8)),
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addMinutes(30),
            'status' => Appointment::STATUS_SCHEDULED,
            'purpose' => fake()->sentence(3),
            'notes' => null,
        ];
    }
}
