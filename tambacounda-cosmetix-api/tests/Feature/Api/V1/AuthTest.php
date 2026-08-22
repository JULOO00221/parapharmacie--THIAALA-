<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Client Test',
            'email' => 'client@example.com',
            'password' => 'SuperSecret123!',
            'password_confirmation' => 'SuperSecret123!',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('user.email', 'client@example.com');
        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('users', ['email' => 'client@example.com']);
    }

    public function test_registration_requires_a_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Client Test',
            'email' => 'taken@example.com',
            'password' => 'SuperSecret123!',
            'password_confirmation' => 'SuperSecret123!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Client Test',
            'email' => 'client@example.com',
            'password' => 'SuperSecret123!',
            'password_confirmation' => 'DoesNotMatch',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_a_registered_customer_can_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('MyPassword123!')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'MyPassword123!',
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('MyPassword123!')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('storefront')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertSame(0, \Laravel\Sanctum\PersonalAccessToken::count());

        // Laravel's auth guards are memoized per test process: without
        // forgetting them, a second call would reuse the guard instance
        // that already resolved a user on the previous request instead of
        // re-validating the (now deleted) token — an artifact of testing
        // two sequential requests in one method, not of production behaviour.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    /**
     * Critical security regression: a customer who self-registers through
     * the public API must never be able to log into the Filament back-office.
     */
    public function test_a_self_registered_customer_cannot_access_the_admin_panel(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/admin')->assertForbidden();
    }

    public function test_a_user_with_the_admin_role_can_access_the_admin_panel(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));

        $this->actingAs($admin)->get('/admin')->assertOk();
    }
}
