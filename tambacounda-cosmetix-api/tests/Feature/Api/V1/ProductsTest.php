<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Tag;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductsTest extends TestCase
{
    use RefreshDatabase;

    private ProductCategory $category;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = ProductCategory::factory()->create(['name' => 'Soins du visage']);
        $this->brand = Brand::factory()->create(['name' => 'Baobab Soins']);
    }

    public function test_index_only_returns_active_products(): void
    {
        Product::factory()->create(['name' => 'Actif', 'category_id' => $this->category->id, 'is_active' => true]);
        Product::factory()->create(['name' => 'Inactif', 'category_id' => $this->category->id, 'is_active' => false]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Actif', $response->json('data.0.name'));
    }

    public function test_index_is_paginated(): void
    {
        Product::factory()->count(5)->create(['category_id' => $this->category->id]);

        $response = $this->getJson('/api/v1/products?per_page=2');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(5, $response->json('meta.total'));
        $this->assertSame(3, $response->json('meta.last_page'));
    }

    public function test_search_filters_by_name(): void
    {
        Product::factory()->create(['name' => 'Savon noir', 'category_id' => $this->category->id]);
        Product::factory()->create(['name' => 'Huile de baobab', 'category_id' => $this->category->id]);

        $response = $this->getJson('/api/v1/products?q=savon');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('Savon noir', $response->json('data.0.name'));
    }

    public function test_filters_by_category_slug(): void
    {
        $other = ProductCategory::factory()->create();
        Product::factory()->create(['category_id' => $this->category->id]);
        Product::factory()->create(['category_id' => $other->id]);

        $response = $this->getJson('/api/v1/products?category='.$this->category->slug);

        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_category_filter_includes_products_of_child_categories(): void
    {
        $serums = ProductCategory::factory()->create(['parent_id' => $this->category->id]);
        $cremes = ProductCategory::factory()->create(['parent_id' => $this->category->id]);
        $unrelated = ProductCategory::factory()->create();

        $direct = Product::factory()->create(['category_id' => $this->category->id]);
        $serum = Product::factory()->create(['category_id' => $serums->id]);
        $creme = Product::factory()->create(['category_id' => $cremes->id]);
        Product::factory()->create(['category_id' => $unrelated->id]);

        $response = $this->getJson('/api/v1/products?category='.$this->category->slug);

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            [$direct->id, $serum->id, $creme->id],
            array_column($response->json('data'), 'id'),
        );

        // A child category still only returns its own products.
        $this->getJson('/api/v1/products?category='.$serums->slug)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $serum->id);
    }

    public function test_category_filter_includes_products_two_levels_down(): void
    {
        $visage = ProductCategory::factory()->create(['parent_id' => $this->category->id]);
        $contourDesYeux = ProductCategory::factory()->create(['parent_id' => $visage->id]);
        $sibling = ProductCategory::factory()->create(['parent_id' => $this->category->id]);

        $atRoot = Product::factory()->create(['category_id' => $this->category->id]);
        $atMiddle = Product::factory()->create(['category_id' => $visage->id]);
        $atLeaf = Product::factory()->create(['category_id' => $contourDesYeux->id]);
        $inSibling = Product::factory()->create(['category_id' => $sibling->id]);

        $idsFor = fn (ProductCategory $category) => array_column(
            $this->getJson('/api/v1/products?category='.$category->slug)->assertOk()->json('data'),
            'id',
        );

        $this->assertEqualsCanonicalizing([$atRoot->id, $atMiddle->id, $atLeaf->id, $inSibling->id], $idsFor($this->category));
        $this->assertEqualsCanonicalizing([$atMiddle->id, $atLeaf->id], $idsFor($visage));
        $this->assertEqualsCanonicalizing([$atLeaf->id], $idsFor($contourDesYeux));
    }

    public function test_category_filter_query_count_does_not_grow_with_the_tree(): void
    {
        // Flat: one category, no children.
        $flat = ProductCategory::factory()->create();
        Product::factory()->create(['category_id' => $flat->id]);

        // Deep: 3 children, each with 3 grandchildren, a product at every node.
        $deep = ProductCategory::factory()->create();
        Product::factory()->create(['category_id' => $deep->id]);
        foreach (range(1, 3) as $i) {
            $child = ProductCategory::factory()->create(['parent_id' => $deep->id]);
            Product::factory()->create(['category_id' => $child->id]);
            foreach (range(1, 3) as $j) {
                $grandchild = ProductCategory::factory()->create(['parent_id' => $child->id]);
                Product::factory()->create(['category_id' => $grandchild->id]);
            }
        }

        $countQueries = function (ProductCategory $category, int $expectedTotal): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/products?category='.$category->slug)
                ->assertOk()
                ->assertJsonPath('meta.total', $expectedTotal);
            DB::disableQueryLog();

            $categoryLookups = array_filter(
                DB::getQueryLog(),
                fn (array $entry) => str_contains($entry['query'], 'product_categories')
                    && ! str_contains($entry['query'], 'WITH RECURSIVE')
                    && ! str_contains($entry['query'], '"product_categories"."id" in'),
            );
            $this->assertSame([], array_values($categoryLookups), 'The category tree must not be resolved by separate queries.');

            return count(DB::getQueryLog());
        };

        $flatQueries = $countQueries($flat, 1);
        $deepQueries = $countQueries($deep, 13);

        $this->assertSame($flatQueries, $deepQueries);
    }

    public function test_filters_by_brand_slug(): void
    {
        $otherBrand = Brand::factory()->create();
        Product::factory()->create(['category_id' => $this->category->id, 'brand_id' => $this->brand->id]);
        Product::factory()->create(['category_id' => $this->category->id, 'brand_id' => $otherBrand->id]);

        $response = $this->getJson('/api/v1/products?brand='.$this->brand->slug);

        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_filters_by_tags(): void
    {
        $tag = Tag::factory()->create(['name' => 'Bio']);
        $productWithTag = Product::factory()->create(['category_id' => $this->category->id]);
        $productWithTag->tags()->attach($tag);
        Product::factory()->create(['category_id' => $this->category->id]);

        $response = $this->getJson('/api/v1/products?tags='.$tag->slug);

        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_filters_by_price_range(): void
    {
        Product::factory()->create(['category_id' => $this->category->id, 'price' => 1000]);
        Product::factory()->create(['category_id' => $this->category->id, 'price' => 5000]);
        Product::factory()->create(['category_id' => $this->category->id, 'price' => 9000]);

        $response = $this->getJson('/api/v1/products?price_min=2000&price_max=6000');

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('5000.00', $response->json('data.0.price'));
    }

    public function test_filters_by_featured(): void
    {
        Product::factory()->create(['category_id' => $this->category->id, 'is_featured' => true]);
        Product::factory()->create(['category_id' => $this->category->id, 'is_featured' => false]);

        $response = $this->getJson('/api/v1/products?featured=1');

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertTrue($response->json('data.0.featured'));
    }

    public function test_filters_by_in_stock(): void
    {
        $store = Store::factory()->create();
        $service = new ProductService();

        $inStock = Product::factory()->create(['category_id' => $this->category->id]);
        $service->setInitialStock($inStock, $store, 10);

        $outOfStock = Product::factory()->create(['category_id' => $this->category->id]);
        $service->setInitialStock($outOfStock, $store, 0);

        $response = $this->getJson('/api/v1/products?in_stock=1');

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($inStock->id, $response->json('data.0.id'));
    }

    public function test_sort_by_price(): void
    {
        Product::factory()->create(['category_id' => $this->category->id, 'name' => 'Cher', 'price' => 9000]);
        Product::factory()->create(['category_id' => $this->category->id, 'name' => 'Pas cher', 'price' => 1000]);

        $response = $this->getJson('/api/v1/products?sort=price_asc');

        $this->assertSame('Pas cher', $response->json('data.0.name'));
        $this->assertSame('Cher', $response->json('data.1.name'));
    }

    public function test_invalid_sort_value_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/products?sort=not_a_real_sort');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('sort');
    }

    public function test_invalid_price_range_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/products?price_min=100&price_max=50');

        $response->assertStatus(422);
    }

    public function test_show_returns_product_by_slug(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id, 'name' => 'Savon test']);

        $response = $this->getJson('/api/v1/products/'.$product->slug);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Savon test');
    }

    public function test_show_returns_404_for_inactive_product(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id, 'is_active' => false]);

        $this->getJson('/api/v1/products/'.$product->slug)->assertNotFound();
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/v1/products/does-not-exist')->assertNotFound();
    }

    public function test_response_never_exposes_internal_fields(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'cost_price' => 500,
            'barcode' => '1234567890123',
        ]);
        $store = Store::factory()->create();
        (new ProductService())->setInitialStock($product, $store, 10, 3, 5);

        $response = $this->getJson('/api/v1/products/'.$product->slug);

        $json = json_encode($response->json());

        foreach (['cost_price', 'barcode', 'quantity_available', 'quantity_reserved', 'alert_threshold', 'margin', 'deleted_at'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json, "La réponse ne doit jamais contenir \"{$forbidden}\".");
        }
    }

    public function test_availability_reflects_sellable_stock(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);
        $store = Store::factory()->create();
        (new ProductService())->setInitialStock($product, $store, 2, 0, 5);

        $response = $this->getJson('/api/v1/products/'.$product->slug);

        $response->assertJsonPath('data.available', true);
        $response->assertJsonPath('data.stock_status', 'low_stock');
    }
}
