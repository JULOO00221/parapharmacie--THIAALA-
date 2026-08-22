<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Tag;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProductService();
    }

    public function test_it_creates_a_product(): void
    {
        $category = ProductCategory::factory()->create();

        $product = $this->service->create([
            'category_id' => $category->id,
            'name' => 'Savon au karite',
            'slug' => 'savon-au-karite',
            'sku' => 'SAV-001',
            'price' => 1500,
        ]);

        $this->assertDatabaseHas('products', ['sku' => 'SAV-001']);
        $this->assertTrue($product->category->is($category));
    }

    public function test_it_rejects_a_compare_at_price_lower_than_price(): void
    {
        $category = ProductCategory::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->service->create([
            'category_id' => $category->id,
            'name' => 'Produit incoherent',
            'slug' => 'produit-incoherent',
            'sku' => 'INC-001',
            'price' => 2000,
            'compare_at_price' => 1000,
        ]);
    }

    public function test_it_updates_a_product_and_validates_price_consistency_against_existing_values(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'compare_at_price' => 1200]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->update($product, ['price' => 1500]);
    }

    public function test_it_toggles_activation(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->service->deactivate($product);
        $this->assertFalse($product->fresh()->is_active);

        $this->service->activate($product);
        $this->assertTrue($product->fresh()->is_active);

        $this->service->toggleActive($product);
        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_it_calculates_margin_when_cost_price_is_known(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'cost_price' => 600]);

        $margin = $this->service->calculateMargin($product);

        $this->assertSame(400.0, $margin['amount']);
        $this->assertSame(40.0, $margin['markup_rate']);
        $this->assertEqualsWithDelta(66.67, $margin['margin_rate'], 0.01);
    }

    public function test_it_returns_null_margin_when_cost_price_is_unknown(): void
    {
        $product = Product::factory()->create(['cost_price' => null]);

        $this->assertNull($this->service->calculateMargin($product));
    }

    public function test_it_adds_images_and_keeps_a_single_primary(): void
    {
        $product = Product::factory()->create();

        $first = $this->service->addImage($product, 'products/1.jpg', isPrimary: true);
        $second = $this->service->addImage($product, 'products/2.jpg', isPrimary: true);

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertCount(2, $product->fresh()->images);
    }

    public function test_it_switches_the_primary_image(): void
    {
        $product = Product::factory()->create();

        $first = $this->service->addImage($product, 'products/1.jpg', isPrimary: true);
        $second = $this->service->addImage($product, 'products/2.jpg', isPrimary: false);

        $this->service->setPrimaryImage($second);

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
    }

    public function test_it_syncs_tags(): void
    {
        $product = Product::factory()->create();
        $tags = Tag::factory()->count(3)->create();

        $this->service->syncTags($product, $tags->pluck('id')->toArray());

        $this->assertCount(3, $product->fresh()->tags);
    }

    public function test_it_creates_and_updates_initial_stock_idempotently(): void
    {
        $product = Product::factory()->create();
        $store = Store::factory()->create();

        $this->service->setInitialStock($product, $store, quantityAvailable: 20, alertThreshold: 5);
        $this->service->setInitialStock($product, $store, quantityAvailable: 35, alertThreshold: 5);

        $this->assertCount(1, $product->fresh()->stocks);
        $this->assertSame(35, $product->fresh()->stocks->first()->quantity_available);
    }

    public function test_stock_is_isolated_per_store(): void
    {
        $product = Product::factory()->create();
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();

        $this->service->setInitialStock($product, $storeA, quantityAvailable: 10);
        $this->service->setInitialStock($product, $storeB, quantityAvailable: 50);

        $this->assertSame(10, $product->stocks()->where('store_id', $storeA->id)->first()->quantity_available);
        $this->assertSame(50, $product->stocks()->where('store_id', $storeB->id)->first()->quantity_available);
    }
}
