<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '+639171234567',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.name', 'Maria Santos')
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'maria@example.com']);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/register', [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_registration_requires_a_matching_password_confirmation(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Someone',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'juan@example.com']);

        $this->postJson('/api/login', [
            'email' => 'juan@example.com',
            'password' => 'password',
        ])->assertOk()->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'juan@example.com']);

        $this->postJson('/api/login', [
            'email' => 'juan@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_deactivated_account_cannot_log_in(): void
    {
        User::factory()->create([
            'email' => 'gone@example.com',
            'is_active' => false,
        ]);

        $this->postJson('/api/login', [
            'email' => 'gone@example.com',
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_authenticated_user_can_read_their_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }
}
