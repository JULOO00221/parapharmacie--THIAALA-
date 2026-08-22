<?php

namespace Tests\Feature\Api\V1;

use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_only_returns_active_categories(): void
    {
        ProductCategory::factory()->create(['name' => 'Active', 'is_active' => true]);
        ProductCategory::factory()->create(['name' => 'Inactive', 'is_active' => false]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_includes_parent_and_children(): void
    {
        $parent = ProductCategory::factory()->create(['name' => 'Parent']);
        ProductCategory::factory()->create(['name' => 'Enfant', 'parent_id' => $parent->id]);

        $response = $this->getJson('/api/v1/categories');

        $child = collect($response->json('data'))->firstWhere('name', 'Enfant');
        $this->assertSame($parent->id, $child['parent']['id']);

        $parentPayload = collect($response->json('data'))->firstWhere('name', 'Parent');
        $this->assertCount(1, $parentPayload['children']);
    }

    public function test_show_returns_category_by_slug(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'Solaires']);

        $this->getJson('/api/v1/categories/'.$category->slug)
            ->assertOk()
            ->assertJsonPath('data.name', 'Solaires');
    }

    public function test_show_returns_404_for_inactive_category(): void
    {
        $category = ProductCategory::factory()->create(['is_active' => false]);

        $this->getJson('/api/v1/categories/'.$category->slug)->assertNotFound();
    }
}
