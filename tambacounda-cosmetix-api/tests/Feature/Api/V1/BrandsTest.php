<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_index_counts_active_products_per_brand(): void
    {
        $brand = Brand::factory()->create(['name' => 'Avène']);
        $empty = Brand::factory()->create(['name' => 'Sans produit']);
        Product::factory()->count(2)->create(['brand_id' => $brand->id]);
        Product::factory()->create(['brand_id' => $brand->id, 'is_active' => false]);
        Product::factory()->create(['brand_id' => $brand->id])->delete();

        $data = collect($this->getJson('/api/v1/brands')->assertOk()->json('data'))->keyBy('name');

        $this->assertSame(2, $data['Avène']['products_count']);
        // Without a category, every active brand is listed, even with no product.
        $this->assertSame(0, $data['Sans produit']['products_count']);
    }

    public function test_category_filter_only_returns_brands_with_products_in_the_subtree(): void
    {
        $visage = ProductCategory::factory()->create();
        $serums = ProductCategory::factory()->create(['parent_id' => $visage->id]);
        $vitC = ProductCategory::factory()->create(['parent_id' => $serums->id]);
        $corps = ProductCategory::factory()->create();

        $avene = Brand::factory()->create(['name' => 'Avène']);
        $bioderma = Brand::factory()->create(['name' => 'Bioderma']);
        $nivea = Brand::factory()->create(['name' => 'Nivea']);
        $inactiveOnly = Brand::factory()->create(['name' => 'Inactif']);

        Product::factory()->count(2)->create(['category_id' => $vitC->id, 'brand_id' => $avene->id]);
        Product::factory()->create(['category_id' => $corps->id, 'brand_id' => $avene->id]);
        Product::factory()->create(['category_id' => $visage->id, 'brand_id' => $bioderma->id]);
        Product::factory()->create(['category_id' => $corps->id, 'brand_id' => $nivea->id]);
        Product::factory()->create(['category_id' => $visage->id, 'brand_id' => $inactiveOnly->id, 'is_active' => false]);

        $data = $this->getJson('/api/v1/brands?category='.$visage->slug)->assertOk()->json('data');

        $this->assertSame(['Avène', 'Bioderma'], array_column($data, 'name'));
        $this->assertSame([2, 1], array_column($data, 'products_count'));

        // Each count is exactly what the product filter returns.
        $this->assertSame(
            2,
            $this->getJson("/api/v1/products?category={$visage->slug}&brand={$avene->slug}")->json('meta.total'),
        );

        $this->assertSame([], $this->getJson('/api/v1/brands?category=inconnue')->assertOk()->json('data'));
    }

    public function test_index_query_count_does_not_grow_with_the_catalogue(): void
    {
        $category = ProductCategory::factory()->create();

        $countQueries = function () use ($category): array {
            $counts = [];
            foreach (['/api/v1/brands', '/api/v1/brands?category='.$category->slug] as $url) {
                DB::flushQueryLog();
                DB::enableQueryLog();
                $this->getJson($url)->assertOk();
                DB::disableQueryLog();
                $counts[] = count(DB::getQueryLog());
            }

            return $counts;
        };

        Product::factory()->create(['category_id' => $category->id, 'brand_id' => Brand::factory()->create()->id]);
        $small = $countQueries();

        foreach (range(1, 8) as $i) {
            Product::factory()->count(2)->create(['category_id' => $category->id, 'brand_id' => Brand::factory()->create()->id]);
        }
        $large = $countQueries();

        $this->assertSame($small, $large);
        $this->assertSame([1, 1], $large);
    }
}
