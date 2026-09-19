<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_reads_are_not_throttled(): void
    {
        foreach (['/api/v1/products', '/api/v1/categories', '/api/v1/brands', '/api/v1/tags', '/api/v1/stores', '/api/v1/delivery-zones'] as $uri) {
            $response = $this->getJson($uri);

            $response->assertOk();
            $response->assertHeaderMissing('X-RateLimit-Limit');
        }
    }

    public function test_many_catalog_reads_from_same_ip_never_return_429(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->getJson('/api/v1/products')->assertOk();
        }
    }

    public function test_login_throttle_is_still_enforced(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'wrong']);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_login_throttle_is_keyed_by_forwarded_client_ip(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->withHeader('X-Forwarded-For', '203.0.113.10')
                ->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'wrong']);
        }

        $this->withHeader('X-Forwarded-For', '203.0.113.10')
            ->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'wrong'])
            ->assertStatus(429);

        // Même proxy, autre visiteur : compteur distinct.
        $this->withHeader('X-Forwarded-For', '203.0.113.20')
            ->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'wrong'])
            ->assertStatus(422);
    }

    public function test_global_api_limit_is_1000_per_minute(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '1000');
    }
}
