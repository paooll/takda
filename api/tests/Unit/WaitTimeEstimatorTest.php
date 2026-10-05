<?php

namespace Tests\Unit;

use App\Models\Queue;
use App\Models\Ticket;
use App\Services\WaitTimeEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitTimeEstimatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_multiplies_people_ahead_by_the_configured_average(): void
    {
        $queue = Queue::factory()->create(['avg_service_minutes' => 15]);

        $estimate = (new WaitTimeEstimator)->estimateSeconds($queue, peopleAhead: 4);

        $this->assertSame(4 * 15 * 60, $estimate);
    }

    public function test_nobody_ahead_means_no_wait(): void
    {
        $queue = Queue::factory()->create(['avg_service_minutes' => 15]);

        $this->assertSame(0, (new WaitTimeEstimator)->estimateSeconds($queue, peopleAhead: 0));
    }

    public function test_new_ticket_position_maps_to_people_ahead(): void
    {
        $queue = Queue::factory()->create(['avg_service_minutes' => 10]);

        // Position 1 means nobody is ahead.
        $this->assertSame(
            0,
            (new WaitTimeEstimator)->estimateForNewTicket($queue, position: 1)
        );

        // Position 6 means 5 people ahead: 5 x 10 min = 3000 seconds.
        $this->assertSame(
            3000,
            (new WaitTimeEstimator)->estimateForNewTicket($queue, position: 6)
        );
    }

    public function test_recent_actual_service_times_override_the_configured_average(): void
    {
        $queue = Queue::factory()->create(['avg_service_minutes' => 10]);

        // Enough history (>= 5 samples) for observed data to be trusted.
        foreach (range(1, 6) as $ignored) {
            Ticket::factory()->done(3)->create([
                'queue_id' => $queue->id,
                'sequence' => $queue->issueTicketNumber(),
            ]);
        }

        $estimate = (new WaitTimeEstimator)->estimateSeconds($queue, peopleAhead: 2);

        // 2 ahead x observed ~3 min, not the configured 10.
        $this->assertSame(360, $estimate);
    }

    public function test_a_small_sample_does_not_override_the_configured_average(): void
    {
        $queue = Queue::factory()->create(['avg_service_minutes' => 10]);

        // Only 2 samples: below the floor, so the configured average still wins.
        foreach (range(1, 2) as $ignored) {
            Ticket::factory()->done(1)->create([
                'queue_id' => $queue->id,
                'sequence' => $queue->issueTicketNumber(),
            ]);
        }

        $this->assertSame(
            2 * 10 * 60,
            (new WaitTimeEstimator)->estimateSeconds($queue, peopleAhead: 2)
        );
    }
}
