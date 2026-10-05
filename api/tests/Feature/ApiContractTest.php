<?php

namespace Tests\Feature;

use App\Models\Appointment;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every list endpoint must return rows at `data` and pagination at `meta`.
     * Laravel's default paginator nests rows under `data.data`, which silently
     * breaks any client that treats `data` as an array.
     */
    #[DataProvider('listEndpoints')]
    public function test_list_endpoints_share_one_envelope(string $endpoint, callable $seed): void
    {
        $user = User::factory()->create();
        $seed($user);

        $response = $this->actingAs($user, 'sanctum')->getJson($endpoint)->assertOk();

        $rows = $response->json('data');

        // Rows sit directly on `data` as a flat list, not nested under data.data.
        $this->assertIsArray($rows, "{$endpoint}: data should be an array");
        $this->assertCount(2, $rows, "{$endpoint}: expected the two seeded rows");
        $this->assertSame(2, $response->json('meta.total'), "{$endpoint}: meta.total");

        $response->assertJsonStructure([
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
    }

    public static function listEndpoints(): array
    {
        return [
            'tickets' => ['/api/tickets', function (User $user) {
                Ticket::factory()->count(2)->create(['user_id' => $user->id]);
            }],
            'appointments' => ['/api/appointments', function (User $user) {
                Appointment::factory()->count(2)->create(['user_id' => $user->id]);
            }],
            'notifications' => ['/api/notifications', function (User $user) {
                Notification::factory()->count(2)->create(['user_id' => $user->id]);
            }],
        ];
    }

    public function test_a_customer_only_sees_their_own_tickets(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();

        $mineTicket = Ticket::factory()->create(['user_id' => $mine->id]);
        Ticket::factory()->create(['user_id' => $theirs->id]);

        $ids = collect($this->actingAs($mine, 'sanctum')->getJson('/api/tickets')->json('data'))
            ->pluck('id');

        $this->assertSame([$mineTicket->id], $ids->all());
    }
}
