<?php

namespace Tests\Feature;

use App\Models\Queue;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_join_a_queue_and_get_a_ticket(): void
    {
        $queue = Queue::factory()->create([
            'avg_service_minutes' => 5,
            'code_prefix' => 'A',
        ]);
        $customer = User::factory()->create();

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/queues/{$queue->id}/join");

        $response->assertCreated()
            ->assertJsonPath('data.status', Ticket::STATUS_WAITING)
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.people_ahead', 0)
            ->assertJsonPath('data.estimated_wait_seconds', 0);

        // First ticket in a fresh queue should be numbered 001.
        $this->assertSame('A-001', $response->json('data.code'));
    }

    public function test_position_and_eta_account_for_people_already_waiting(): void
    {
        $queue = Queue::factory()->create(['avg_service_minutes' => 10]);
        $customer = User::factory()->create();

        // Two people already waiting. Issue their numbers through the queue's
        // own allocator so `last_issued_number` stays in sync with the tickets.
        foreach (range(1, 2) as $ignored) {
            Ticket::factory()->create([
                'queue_id' => $queue->id,
                'sequence' => $queue->issueTicketNumber(),
                'user_id' => User::factory(),
            ]);
        }

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/queues/{$queue->id}/join");

        $response->assertCreated()
            ->assertJsonPath('data.position', 3)
            ->assertJsonPath('data.people_ahead', 2)
            // 2 ahead x 10 min x 60s
            ->assertJsonPath('data.estimated_wait_seconds', 1200);
    }

    public function test_ticket_numbers_do_not_collide_across_joins(): void
    {
        $queue = Queue::factory()->create();

        $codes = collect(range(1, 5))->map(function () use ($queue) {
            $customer = User::factory()->create();

            return $this->actingAs($customer, 'sanctum')
                ->postJson("/api/queues/{$queue->id}/join")
                ->json('data.code');
        });

        $this->assertCount(5, $codes->unique(), "Duplicate ticket codes: {$codes->implode(', ')}");
    }

    public function test_joining_twice_returns_the_existing_ticket_instead_of_a_second_one(): void
    {
        $queue = Queue::factory()->create();
        $customer = User::factory()->create();

        $first = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/queues/{$queue->id}/join");

        $second = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/queues/{$queue->id}/join");

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, $customer->tickets()->count());
    }

    public function test_calling_people_ahead_moves_the_customer_up(): void
    {
        $queue = Queue::factory()->create(['avg_service_minutes' => 10]);
        $customer = User::factory()->create();

        $ahead = Ticket::factory()->create([
            'queue_id' => $queue->id,
            'sequence' => $queue->issueTicketNumber(),
            'user_id' => User::factory(),
        ]);

        $mine = Ticket::factory()->create([
            'queue_id' => $queue->id,
            'sequence' => $queue->issueTicketNumber(),
            'user_id' => $customer,
        ]);

        $this->assertSame(2, $mine->position());

        $ahead->update(['status' => Ticket::STATUS_SERVING, 'called_at' => now()]);

        $this->assertSame(1, $mine->fresh()->position());
    }

    public function test_customer_can_leave_the_queue(): void
    {
        $queue = Queue::factory()->create();
        $customer = User::factory()->create();

        $ticketId = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/queues/{$queue->id}/join")
            ->json('data.id');

        $this->actingAs($customer, 'sanctum')
            ->deleteJson("/api/tickets/{$ticketId}")
            ->assertOk();

        $this->assertSame(
            Ticket::STATUS_CANCELLED,
            Ticket::find($ticketId)->status
        );
    }

    public function test_customer_cannot_view_or_cancel_someone_elses_ticket(): void
    {
        $ticket = Ticket::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/tickets/{$ticket->id}")
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->deleteJson("/api/tickets/{$ticket->id}")
            ->assertForbidden();
    }

    public function test_guest_cannot_join_a_queue(): void
    {
        $queue = Queue::factory()->create();

        $this->postJson("/api/queues/{$queue->id}/join")->assertUnauthorized();
    }

    public function test_inactive_queue_rejects_new_joins(): void
    {
        $queue = Queue::factory()->create(['is_active' => false]);
        $customer = User::factory()->create();

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/queues/{$queue->id}/join")
            ->assertStatus(422);
    }
}
