<?php

namespace Tests\Feature\Api\V1;

use App\Models\DeliveryZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryZonesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_ok_when_the_table_is_empty(): void
    {
        // Aucune zone fictive créée — la table est réellement vide à ce
        // stade du projet, l'endpoint doit rester fonctionnel malgré tout.
        $response = $this->getJson('/api/v1/delivery-zones');

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }

    public function test_index_returns_active_zones(): void
    {
        DeliveryZone::create(['name' => 'Centre-ville', 'fee' => 1000, 'is_active' => true]);
        DeliveryZone::create(['name' => 'Périphérie', 'fee' => 2000, 'is_active' => true]);

        $response = $this->getJson('/api/v1/delivery-zones');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_inactive_zones_are_excluded(): void
    {
        DeliveryZone::create(['name' => 'Zone active', 'fee' => 1000, 'is_active' => true]);
        DeliveryZone::create(['name' => 'Zone désactivée', 'fee' => 1500, 'is_active' => false]);

        $response = $this->getJson('/api/v1/delivery-zones');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Zone active', $response->json('data.0.name'));
    }

    public function test_index_is_not_paginated(): void
    {
        DeliveryZone::create(['name' => 'Zone A', 'fee' => 500, 'is_active' => true]);

        $response = $this->getJson('/api/v1/delivery-zones');

        $response->assertOk();
        $this->assertIsArray($response->json('data'));
        $this->assertNull($response->json('meta'));
        $this->assertNull($response->json('links'));
    }

    public function test_response_shape_and_no_sensitive_data(): void
    {
        $zone = DeliveryZone::create(['name' => 'Centre-ville', 'fee' => 1000, 'is_active' => true]);

        $response = $this->getJson('/api/v1/delivery-zones');

        $response->assertOk();
        $response->assertJsonPath('data.0', [
            'id' => $zone->id,
            'name' => $zone->name,
            'fee' => $zone->fee,
        ]);

        $this->assertStringNotContainsString('"is_active"', $response->getContent());
        $this->assertStringNotContainsString('"created_at"', $response->getContent());
        $this->assertStringNotContainsString('"updated_at"', $response->getContent());
    }
}
