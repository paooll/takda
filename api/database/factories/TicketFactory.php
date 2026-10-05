<?php

namespace Database\Factories;

use App\Models\Queue;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'queue_id' => Queue::factory(),
            'user_id' => User::factory(),
            'sequence' => fake()->unique()->numberBetween(1, 100000),
            'code' => fake()->unique()->bothify('??-###'),
            'status' => Ticket::STATUS_WAITING,
            'estimated_wait_seconds' => 0,
            'joined_at' => now(),
        ];
    }

    /** A ticket already at the counter. */
    public function serving(): static
    {
        return $this->state(fn () => [
            'status' => Ticket::STATUS_SERVING,
            'called_at' => now(),
        ]);
    }

    /** A finished ticket, optionally with service timings for ETA history. */
    public function done(int $serviceMinutes = 10): static
    {
        return $this->state(fn () => [
            'status' => Ticket::STATUS_DONE,
            'called_at' => now()->subMinutes($serviceMinutes),
            'completed_at' => now(),
        ]);
    }
}
