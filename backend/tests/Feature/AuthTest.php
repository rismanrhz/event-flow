<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_registration_creates_participant_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New User',
            'email' => 'new.user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email', 'role' => ['key', 'label']], 'token']]);

        $this->assertDatabaseHas('users', [
            'email' => 'new.user@example.com',
            'role_id' => Role::where('key', 'participant')->value('id'),
        ]);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Another',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('email');
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New User',
            'email' => 'noconfirm@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $password = 'secretpass123';
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make($password),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => $password,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'login@example.com')
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_login_ignores_extra_boolean_fields(): void
    {
        $password = 'secretpass123';
        $user = User::factory()->create([
            'email' => 'remembered@example.com',
            'password' => Hash::make($password),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'remembered@example.com',
            'password' => $password,
            'remember' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'remembered@example.com');
    }

    public function test_login_validation_errors(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
            'password' => '',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_rejects_deactivated_account(): void
    {
        $user = User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => Hash::make('secretpass123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'disabled@example.com',
            'password' => 'secretpass123',
        ]);

        $response->assertStatus(403);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_me_returns_current_user(): void
    {
        $user = $this->userWithRole('participant', ['name' => 'Me Test']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'Me Test')
            ->assertJsonPath('data.role.key', 'participant');
    }

    public function test_logout_revokes_access_token(): void
    {
        $user = $this->userWithRole('participant');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/auth/logout');
        $response->assertOk()->assertJsonPath('success', true);

        // The Sanctum guard is cached between requests inside a single PHPUnit test,
        // so reset it to force a fresh token lookup.
        app('auth')->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_change_password_rejects_incorrect_current_password(): void
    {
        $user = $this->userWithRole('participant');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'wrongpassword',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
    }

    public function test_change_password_updates_password_and_revokes_other_tokens(): void
    {
        $user = $this->userWithRole('participant');
        $user->createToken('other-session');
        $current = $user->createToken('current-session');

        $response = $this->withToken($current->plainTextToken)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'password',
            'new_password' => 'brandnewpass123',
            'new_password_confirmation' => 'brandnewpass123',
        ]);

        $response->assertOk();

        $this->assertTrue(Hash::check('brandnewpass123', $user->fresh()->password));
        // The revoked token count drops to the current session only.
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // The current session stays usable after the password change.
        $this->withToken($current->plainTextToken)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_forgot_password_returns_reset_url_in_local_dev(): void
    {
        $user = User::factory()->create(['email' => 'forgot@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'forgot@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['reset_url']]);
    }

    public function test_reset_password_with_valid_token_works(): void
    {
        $user = User::factory()->create(['email' => 'resetme@example.com']);
        $token = app('auth.password.broker')->createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'resetme@example.com',
            'password' => 'resetpass123',
            'password_confirmation' => 'resetpass123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertTrue(Hash::check('resetpass123', $user->fresh()->password));
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'resettwo@example.com']);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => 'resettwo@example.com',
            'password' => 'resetpass123',
            'password_confirmation' => 'resetpass123',
        ]);

        $response->assertStatus(422);
    }

    public function test_profile_update_persists_changes(): void
    {
        $user = $this->userWithRole('participant');
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/auth/profile', [
            'name' => 'Updated Name',
            'email' => $user->email,
            'phone' => '081234567890',
            'institution' => 'Airlangga University',
            'job_title' => 'Student',
            'address' => 'Surabaya, East Java',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.institution', 'Airlangga University');
    }

    public function test_sessions_lists_and_revokes_tokens(): void
    {
        $user = $this->userWithRole('participant');
        $token = $user->createToken('session-a');
        $user->createToken('session-b');

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $revokeId = $user->tokens()->where('name', 'session-b')->first()->id;

        $this->withToken($token->plainTextToken)
            ->deleteJson("/api/v1/auth/sessions/{$revokeId}")
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
