<?php

namespace Tests\Feature\Import;

use App\Filament\Pages\ImportProducts;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ImportProductsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->create();
        $this->admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));
        $this->store = Store::factory()->create();
        ProductCategory::factory()->create(['name' => 'Soins du visage']);
    }

    private function fakeCsv(): UploadedFile
    {
        $content = "Nom;SKU;Prix;Catégorie;Stock\n"
            ."Savon page test;SKU-PAGE-1;1500;Soins du visage;12\n";

        return UploadedFile::fake()->createWithContent('produits.csv', $content);
    }

    public function test_admin_can_access_the_import_page(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/import-products')->assertOk();
    }

    public function test_the_full_wizard_imports_a_product_end_to_end(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(ImportProducts::class)
            ->set('data.csv_file', $this->fakeCsv())
            ->set('data.store_id', $this->store->id)
            ->set('data.category_policy', 'create')
            ->set('data.brand_policy', 'create')
            ->set('data.tag_policy', 'create')
            ->call('analyzeFile');

        $component->assertSet('step', 2);

        $component->call('generatePreview');
        $component->assertSet('step', 3);
        $this->assertSame(1, $component->get('previewSummary')['created']);
        $this->assertDatabaseMissing('products', ['sku' => 'SKU-PAGE-1']);

        $component->callAction('runImport');
        $component->assertSet('step', 4);

        $this->assertDatabaseHas('products', ['sku' => 'SKU-PAGE-1', 'name' => 'Savon page test']);
        $product = Product::where('sku', 'SKU-PAGE-1')->firstOrFail();
        $this->assertSame(1, $product->stocks()->count());
        $this->assertSame(12, $product->stocks()->first()->quantity_available);
    }

    public function test_a_second_confirmed_import_of_the_same_file_does_not_duplicate_the_product(): void
    {
        $this->actingAs($this->admin);

        $run = function () {
            return Livewire::test(ImportProducts::class)
                ->set('data.csv_file', $this->fakeCsv())
                ->set('data.store_id', $this->store->id)
                ->set('data.category_policy', 'create')
                ->set('data.brand_policy', 'create')
                ->set('data.tag_policy', 'create')
                ->call('analyzeFile')
                ->call('generatePreview')
                ->callAction('runImport');
        };

        $run();
        $run();

        $this->assertSame(1, Product::where('sku', 'SKU-PAGE-1')->count());
    }
}
