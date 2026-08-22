<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_can_be_created_with_required_fields(): void
    {
        $product = Product::factory()->create(['name' => 'Creme hydratante']);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Creme hydratante',
        ]);
    }

    public function test_sku_must_be_unique(): void
    {
        $existing = Product::factory()->create(['sku' => 'SKU-0001']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Product::factory()->create(['sku' => 'SKU-0001']);
    }

    public function test_product_category_supports_parent_child_hierarchy(): void
    {
        $parent = ProductCategory::factory()->create(['name' => 'Soins visage']);
        $child = ProductCategory::factory()->create([
            'name' => 'Cremes',
            'parent_id' => $parent->id,
        ]);

        $this->assertTrue($child->parent->is($parent));
        $this->assertTrue($parent->children->first()->is($child));
    }

    public function test_a_category_with_children_cannot_be_deleted(): void
    {
        $parent = ProductCategory::factory()->create();
        ProductCategory::factory()->create(['parent_id' => $parent->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('product_categories')->where('id', $parent->id)->delete();
    }

    public function test_brand_has_many_products(): void
    {
        $brand = Brand::factory()->create();
        Product::factory()->count(3)->create(['brand_id' => $brand->id]);

        $this->assertCount(3, $brand->products);
        $this->assertTrue($brand->products->first()->brand->is($brand));
    }

    public function test_category_has_many_products(): void
    {
        $category = ProductCategory::factory()->create();
        Product::factory()->count(2)->create(['category_id' => $category->id]);

        $this->assertCount(2, $category->products);
    }

    public function test_a_product_can_have_multiple_tags(): void
    {
        $product = Product::factory()->create();
        $tags = Tag::factory()->count(2)->create();

        $product->tags()->attach($tags->pluck('id'));

        $this->assertCount(2, $product->fresh()->tags);
    }

    public function test_only_one_primary_image_is_allowed_per_product_at_database_level(): void
    {
        $product = Product::factory()->create();

        $product->images()->create(['path' => 'products/a.jpg', 'is_primary' => true]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $product->images()->create(['path' => 'products/b.jpg', 'is_primary' => true]);
    }

    public function test_stock_is_scoped_by_store_and_unique_per_product_and_store(): void
    {
        $product = Product::factory()->create();
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();

        $product->stocks()->create(['store_id' => $storeA->id, 'quantity_available' => 10]);
        $product->stocks()->create(['store_id' => $storeB->id, 'quantity_available' => 5]);

        $this->assertCount(2, $product->fresh()->stocks);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $product->stocks()->create(['store_id' => $storeA->id, 'quantity_available' => 99]);
    }

    public function test_stock_quantity_cannot_be_negative(): void
    {
        $product = Product::factory()->create();
        $store = Store::factory()->create();

        $this->expectException(\Illuminate\Database\QueryException::class);

        $product->stocks()->create(['store_id' => $store->id, 'quantity_available' => -1]);
    }
}
