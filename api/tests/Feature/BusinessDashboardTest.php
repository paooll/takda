<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Notification;
use App\Models\Queue;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_the_live_board_in_join_order(): void
    {
        [$owner, $business, $queue] = $this->setUpBusiness();

        Ticket::factory()->create([
            'queue_id' => $queue->id,
            'sequence' => $queue->issueTicketNumber(),
            'user_id' => User::factory(),
        ]);
        Ticket::factory()->create([
            'queue_id' => $queue->id,
            'sequence' => $queue->issueTicketNumber(),
            'user_id' => User::factory(),
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/businesses/{$business->slug}/queues/{$queue->id}/board")
            ->assertOk()
            ->assertJsonPath('data.queue.waiting_count', 2)
            ->assertJsonCount(2, 'data.tickets')
            ->assertJsonPath('data.tickets.0.position', 1)
            ->assertJsonPath('data.tickets.1.position', 2);
    }

    public function test_stranger_cannot_view_the_board(): void
    {
        [, $business, $queue] = $this->setUpBusiness();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/businesses/{$business->slug}/queues/{$queue->id}/board")
            ->assertForbidden();
    }

    public function test_call_next_promotes_the_first_waiter_and_notifies_them(): void
    {
        [$owner, $business, $queue] = $this->setUpBusiness();
        $customer = User::factory()->create();

        Ticket::factory()->create([
            'queue_id' => $queue->id,
            'sequence' => $queue->issueTicketNumber(),
            'user_id' => $customer,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/businesses/{$business->slug}/queues/{$queue->id}/call-next")
            ->assertOk();

        $this->assertSame(1, $customer->tickets()->where('status', Ticket::STATUS_SERVING)->count());

        $this->assertNotNull(
            $customer->notifications()->where('type', Notification::TYPE_CALLED)->first()
        );
    }

    public function test_call_next_on_an_empty_queue_reports_not_found(): void
    {
        [$owner, $business, $queue] = $this->setUpBusiness();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/businesses/{$business->slug}/queues/{$queue->id}/call-next")
            ->assertNotFound();
    }

    public function test_completing_a_served_ticket_records_the_finish(): void
    {
        [$owner, $business, $queue] = $this->setUpBusiness();

        $ticket = Ticket::factory()->serving()->create([
            'queue_id' => $queue->id,
            'sequence' => $queue->issueTicketNumber(),
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/businesses/{$business->slug}/queues/{$queue->id}/tickets/{$ticket->id}/complete")
            ->assertOk();

        $this->assertSame(Ticket::STATUS_DONE, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->completed_at);
    }

    public function test_a_waiting_ticket_cannot_be_completed(): void
    {
        [$owner, $business, $queue] = $this->setUpBusiness();

        $ticket = Ticket::factory()->create([
            'queue_id' => $queue->id,
            'sequence' => $queue->issueTicketNumber(),
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/businesses/{$business->slug}/queues/{$queue->id}/tickets/{$ticket->id}/complete")
            ->assertStatus(422);
    }

    public function test_analytics_reports_throughput_and_waits(): void
    {
        [$owner, $business, $queue] = $this->setUpBusiness();

        Ticket::factory()->done(12)->create([
            'queue_id' => $queue->id,
            'sequence' => $queue->issueTicketNumber(),
        ]);
        Ticket::factory()->done(8)->create([
            'queue_id' => $queue->id,
            'sequence' => $queue->issueTicketNumber(),
        ]);
        Ticket::factory()->create([
            'queue_id' => $queue->id,
            'sequence' => $queue->issueTicketNumber(),
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/businesses/{$business->slug}/dashboard")
            ->assertOk()
            ->assertJsonPath('data.served_30d', 2)
            ->assertJsonPath('data.waiting_now', 1)
            ->assertJsonPath('data.queues.0.name', $queue->name);
    }

    /** @return array{0: User, 1: Business, 2: Queue} */
    private function setUpBusiness(): array
    {
        $owner = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business = Business::factory()->create(['owner_id' => $owner->id]);
        $queue = Queue::factory()->create(['business_id' => $business->id]);

        return [$owner, $business, $queue];
    }
}
