<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_only_returns_active_brands(): void
    {
        Brand::factory()->create(['name' => 'Active', 'is_active' => true]);
        Brand::factory()->create(['name' => 'Inactive', 'is_active' => false]);

        $response = $this->getJson('/api/v1/brands');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_show_returns_brand_by_slug(): void
    {
        $brand = Brand::factory()->create(['name' => 'Teranga Cosmétique']);

        $this->getJson('/api/v1/brands/'.$brand->slug)
            ->assertOk()
            ->assertJsonPath('data.name', 'Teranga Cosmétique');
    }

    public function test_show_returns_404_for_inactive_brand(): void
    {
        $brand = Brand::factory()->create(['is_active' => false]);

        $this->getJson('/api/v1/brands/'.$brand->slug)->assertNotFound();
    }
}
