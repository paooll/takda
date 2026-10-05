<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_book_an_appointment(): void
    {
        $business = Business::factory()->create();
        $customer = User::factory()->create();

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/appointments', [
                'business_id' => $business->id,
                'starts_at' => now()->addDay()->toDateTimeString(),
                'purpose' => 'Renew passport',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.purpose', 'Renew passport')
            ->assertJsonPath('data.status', Appointment::STATUS_SCHEDULED);

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_double_booking_the_same_slot_is_rejected(): void
    {
        $business = Business::factory()->create();
        $slot = now()->addDay()->startOfHour();

        Appointment::factory()->create([
            'business_id' => $business->id,
            'starts_at' => $slot,
        ]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/appointments', [
                'business_id' => $business->id,
                'starts_at' => $slot->toDateTimeString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_past_appointments_cannot_be_booked(): void
    {
        $business = Business::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/appointments', [
                'business_id' => $business->id,
                'starts_at' => now()->subDay()->toDateTimeString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_customer_can_cancel_their_own_appointment(): void
    {
        $customer = User::factory()->create();
        $appointment = Appointment::factory()->create(['user_id' => $customer->id]);

        $this->actingAs($customer, 'sanctum')
            ->deleteJson("/api/appointments/{$appointment->id}")
            ->assertOk();

        $this->assertSame(Appointment::STATUS_CANCELLED, $appointment->fresh()->status);
    }

    public function test_customer_cannot_cancel_someone_elses_appointment(): void
    {
        $appointment = Appointment::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson("/api/appointments/{$appointment->id}")
            ->assertForbidden();
    }
}
