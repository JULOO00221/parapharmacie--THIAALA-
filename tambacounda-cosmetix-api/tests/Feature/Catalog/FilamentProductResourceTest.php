<?php

namespace Tests\Feature\Catalog;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\RelationManagers\ImagesRelationManager;
use App\Filament\Resources\Products\RelationManagers\StocksRelationManager;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FilamentProductResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function resourceIndexUrls(): array
    {
        return [
            'product categories' => ['/admin/product-categories'],
            'brands' => ['/admin/brands'],
            'products' => ['/admin/products'],
            'tags' => ['/admin/tags'],
            'stocks' => ['/admin/stocks'],
        ];
    }

    #[DataProvider('resourceIndexUrls')]
    public function test_admin_can_view_resource_index_and_create_pages(string $indexUrl): void
    {
        $this->actingAs($this->admin);

        $this->get($indexUrl)->assertOk();
        $this->get($indexUrl.'/create')->assertOk();
    }

    public function test_admin_can_view_a_product_edit_page(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();

        $this->get("/admin/products/{$product->id}/edit")->assertOk();
    }

    public function test_admin_can_create_a_product_through_the_filament_form(): void
    {
        $this->actingAs($this->admin);

        $category = ProductCategory::factory()->create();
        $brand = Brand::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Huile de baobab',
                'slug' => 'huile-de-baobab',
                'sku' => 'HDB-001',
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'price' => 3000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', ['sku' => 'HDB-001']);
    }

    public function test_images_relation_manager_keeps_a_single_primary_image(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();
        $first = $product->images()->create(['path' => 'products/1.jpg', 'is_primary' => true]);
        $second = $product->images()->create(['path' => 'products/2.jpg', 'is_primary' => false]);

        Livewire::test(ImagesRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => \App\Filament\Resources\Products\Pages\EditProduct::class,
        ])
            ->callTableAction('setPrimary', $second);

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
    }

    public function test_stocks_relation_manager_prevents_duplicate_store_via_validation(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();
        $store = Store::factory()->create();
        $product->stocks()->create(['store_id' => $store->id, 'quantity_available' => 10]);

        Livewire::test(StocksRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => \App\Filament\Resources\Products\Pages\EditProduct::class,
        ])
            ->callTableAction('create', data: [
                'store_id' => $store->id,
                'quantity_available' => 99,
                'quantity_reserved' => 0,
            ])
            ->assertHasActionErrors();

        $this->assertSame(1, $product->stocks()->where('store_id', $store->id)->count());
        $this->assertSame(10, $product->stocks()->where('store_id', $store->id)->first()->quantity_available);
    }
}
