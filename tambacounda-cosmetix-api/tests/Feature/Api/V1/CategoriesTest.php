<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_index_counts_active_products_of_the_whole_subtree(): void
    {
        $visage = ProductCategory::factory()->create(['name' => 'Soins du visage']);
        $serums = ProductCategory::factory()->create(['name' => 'Sérums', 'parent_id' => $visage->id]);
        $vitC = ProductCategory::factory()->create(['name' => 'Vitamine C', 'parent_id' => $serums->id]);
        $empty = ProductCategory::factory()->create(['name' => 'Vide']);

        Product::factory()->create(['category_id' => $visage->id]);
        Product::factory()->count(2)->create(['category_id' => $serums->id]);
        Product::factory()->count(3)->create(['category_id' => $vitC->id]);
        Product::factory()->create(['category_id' => $vitC->id, 'is_active' => false]);
        Product::factory()->create(['category_id' => $vitC->id])->delete();

        $data = collect($this->getJson('/api/v1/categories')->assertOk()->json('data'))->keyBy('name');

        $this->assertSame(6, $data['Soins du visage']['products_count']);
        $this->assertSame(5, $data['Sérums']['products_count']);
        $this->assertSame(3, $data['Vitamine C']['products_count']);
        $this->assertSame(0, $data['Vide']['products_count']);
        // Embedded children carry their own subtree count too.
        $this->assertSame(5, $data['Soins du visage']['children'][0]['products_count']);

        // Each count is exactly what the product filter returns.
        foreach ([$visage, $serums, $vitC, $empty] as $category) {
            $this->assertSame(
                $data[$category->name]['products_count'],
                $this->getJson('/api/v1/products?category='.$category->slug)->json('meta.total'),
            );
        }

        $this->getJson('/api/v1/categories/'.$serums->slug)->assertOk()->assertJsonPath('data.products_count', 5);
    }

    public function test_index_counts_can_be_limited_to_one_brand(): void
    {
        $parent = ProductCategory::factory()->create(['name' => 'Parent']);
        $child = ProductCategory::factory()->create(['name' => 'Enfant', 'parent_id' => $parent->id]);
        $avene = Brand::factory()->create();
        $other = Brand::factory()->create();

        Product::factory()->count(2)->create(['category_id' => $child->id, 'brand_id' => $avene->id]);
        Product::factory()->create(['category_id' => $parent->id, 'brand_id' => $other->id]);

        $data = collect($this->getJson('/api/v1/categories?brand='.$avene->slug)->assertOk()->json('data'))->keyBy('name');

        $this->assertSame(2, $data['Parent']['products_count']);
        $this->assertSame(2, $data['Enfant']['products_count']);
    }

    public function test_index_query_count_does_not_grow_with_the_catalogue(): void
    {
        $countQueries = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/categories')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        // One child from the start: with no parent_id at all, Laravel skips
        // the `parent` eager load entirely, which would skew the comparison.
        $root = ProductCategory::factory()->create();
        $firstChild = ProductCategory::factory()->create(['parent_id' => $root->id]);
        Product::factory()->create(['category_id' => $firstChild->id]);
        $small = $countQueries();

        foreach (range(1, 5) as $i) {
            $child = ProductCategory::factory()->create(['parent_id' => $root->id]);
            $grandchild = ProductCategory::factory()->create(['parent_id' => $child->id]);
            Product::factory()->count(2)->create(['category_id' => $grandchild->id]);
        }
        $large = $countQueries();

        $this->assertSame($small, $large);
        // Categories, parents, children, grouped product counts, category tree.
        $this->assertSame(5, $large);
    }
}
