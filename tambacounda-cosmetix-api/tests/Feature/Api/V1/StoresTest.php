<?php

namespace Tests\Feature\Api\V1;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_active_stores(): void
    {
        Store::factory()->create(['name' => 'Tambacounda Centre', 'is_active' => true]);
        Store::factory()->create(['name' => 'Boutique Nord', 'is_active' => true]);

        $response = $this->getJson('/api/v1/stores');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_inactive_stores_are_excluded(): void
    {
        Store::factory()->create(['is_active' => true]);
        Store::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/stores');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_is_not_paginated(): void
    {
        Store::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/stores');

        $response->assertOk();
        $this->assertIsArray($response->json('data'));
        $this->assertNull($response->json('meta'));
        $this->assertNull($response->json('links'));
    }

    public function test_response_shape_and_no_sensitive_data(): void
    {
        $store = Store::factory()->create(['name' => 'Tambacounda Centre', 'is_active' => true]);

        $response = $this->getJson('/api/v1/stores');

        $response->assertOk();
        $response->assertJsonPath('data.0', [
            'id' => $store->id,
            'name' => $store->name,
            'slug' => $store->slug,
        ]);

        // is_active et les timestamps sont un état interne, jamais exposés.
        $this->assertStringNotContainsString('"is_active"', $response->getContent());
        $this->assertStringNotContainsString('"created_at"', $response->getContent());
        $this->assertStringNotContainsString('"updated_at"', $response->getContent());
    }
}
