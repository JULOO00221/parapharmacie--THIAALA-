<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Tag;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_produce_no_duplicates_on_a_second_run(): void
    {
        $this->seed(DatabaseSeeder::class);

        $counts = [
            'stores' => Store::count(),
            'brands' => Brand::count(),
            'categories' => ProductCategory::count(),
            'tags' => Tag::count(),
            'products' => Product::count(),
            'stocks' => Stock::count(),
        ];

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts['stores'], Store::count());
        $this->assertSame($counts['brands'], Brand::count());
        $this->assertSame($counts['categories'], ProductCategory::count());
        $this->assertSame($counts['tags'], Tag::count());
        $this->assertSame($counts['products'], Product::count());
        $this->assertSame($counts['stocks'], Stock::count());
    }

    public function test_seeded_counts_are_within_the_requested_ranges(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Store::count());
        $this->assertGreaterThanOrEqual(8, ProductCategory::count());
        $this->assertLessThanOrEqual(12, ProductCategory::count());
        $this->assertGreaterThanOrEqual(8, Brand::count());
        $this->assertLessThanOrEqual(10, Brand::count());
        $this->assertGreaterThanOrEqual(20, Product::count());
        $this->assertLessThanOrEqual(30, Product::count());
        $this->assertGreaterThanOrEqual(10, Tag::count());
        $this->assertLessThanOrEqual(15, Tag::count());
    }

    public function test_product_skus_are_globally_unique(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(Product::count(), Product::distinct('sku')->count('sku'));
    }

    public function test_categories_form_a_parent_child_hierarchy(): void
    {
        $this->seed(DatabaseSeeder::class);

        $parents = ProductCategory::whereNull('parent_id')->get();
        $children = ProductCategory::whereNotNull('parent_id')->get();

        $this->assertGreaterThan(0, $parents->count());
        $this->assertGreaterThan(0, $children->count());

        foreach ($children as $child) {
            $this->assertTrue($parents->contains('id', $child->parent_id));
        }
    }

    public function test_products_have_correct_category_brand_and_tag_relations(): void
    {
        $this->seed(DatabaseSeeder::class);

        $product = Product::where('sku', 'TC-0001')->firstOrFail();

        $this->assertNotNull($product->category);
        $this->assertNotNull($product->brand);
        $this->assertSame('Baobab Soins', $product->brand->name);
        $this->assertTrue($product->tags->pluck('name')->contains('Bio'));
    }

    public function test_stock_is_created_only_for_the_seeded_store(): void
    {
        $this->seed(DatabaseSeeder::class);

        $store = Store::where('slug', 'tambacounda-cosmetix')->firstOrFail();
        $product = Product::where('sku', 'TC-0001')->firstOrFail();

        $stock = $product->stocks()->first();

        $this->assertNotNull($stock);
        $this->assertSame($store->id, $stock->store_id);
        $this->assertSame(1, $product->stocks()->count());
    }

    public function test_every_seeded_product_has_stock_at_the_store(): void
    {
        $this->seed(DatabaseSeeder::class);

        $store = Store::where('slug', 'tambacounda-cosmetix')->firstOrFail();

        $productsWithoutStock = Product::whereDoesntHave('stocks', function ($query) use ($store) {
            $query->where('store_id', $store->id);
        })->count();

        $this->assertSame(0, $productsWithoutStock);
    }
}
