<?php

namespace Tests\Feature\Import;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Tag;
use App\Services\Imports\Dto\ImportRowResult;
use App\Services\Imports\ProductCsvImportService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProductCsvImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductCsvImportService $service;

    private Store $store;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProductCsvImportService(new ProductService());
        $this->store = Store::factory()->create();
        $this->category = ProductCategory::factory()->create(['name' => 'Soins du visage', 'slug' => 'soins-du-visage']);
    }

    private function writeCsv(string $header, array $lines, string $delimiter = ';'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csvtest_');
        file_put_contents($path, $header."\n".implode("\n", $lines)."\n");

        return $path;
    }

    private function defaultPolicies(): array
    {
        return ['category' => 'create', 'brand' => 'create', 'tag' => 'create'];
    }

    private function header(): string
    {
        return 'Nom;SKU;Code-barres;Prix;Prix d\'achat;Prix barré;TVA;Catégorie;Sous-catégorie;Marque;Description courte;Description;Actif;Mis en avant;Ordonnance;Poids;Tags;Stock';
    }

    /**
     * name;sku;barcode;price;cost_price;compare_at_price;tax_rate;category;subcategory;brand;short_desc;desc;is_active;is_featured;prescription;weight;tags;stock
     */
    private function row(array $overrides = []): string
    {
        $defaults = [
            'Savon test', 'SKU-TEST-1', '', '2000', '', '', '', 'Soins du visage', '', '', '', '', '', '', '', '', '', '',
        ];

        $keys = ['name', 'sku', 'barcode', 'price', 'cost_price', 'compare_at_price', 'tax_rate', 'category', 'subcategory', 'brand', 'short', 'desc', 'active', 'featured', 'prescription', 'weight', 'tags', 'stock'];
        $values = array_combine($keys, $defaults);
        $values = array_merge($values, $overrides);

        return implode(';', array_values($values));
    }

    // 1. Valid CSV end to end.
    public function test_valid_csv_imports_successfully(): void
    {
        $path = $this->writeCsv($this->header(), [
            $this->row(['sku' => 'SKU-0001', 'name' => 'Savon karite']),
        ]);

        $mapping = $this->service->analyze($path)['mapping'];
        $report = $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $this->assertSame(1, $report->created);
        $this->assertSame(0, $report->errors);
        $this->assertDatabaseHas('products', ['sku' => 'SKU-0001', 'name' => 'Savon karite']);
    }

    // 2. Missing required columns detected at analyze().
    public function test_analyze_detects_missing_required_columns(): void
    {
        $path = $this->writeCsv('Nom;Catégorie', ['Savon;Soins du visage']);

        $analysis = $this->service->analyze($path);

        $this->assertContains('sku', $analysis['missing_required']);
        $this->assertContains('price', $analysis['missing_required']);
    }

    // 3. Missing SKU on a row.
    public function test_row_without_sku_is_an_error(): void
    {
        $path = $this->writeCsv($this->header(), [$this->row(['sku' => ''])]);

        $mapping = $this->service->analyze($path)['mapping'];
        $report = $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $this->assertSame(1, $report->errors);
        $this->assertStringContainsString('SKU manquant', $report->rows[0]->errors[0]);
    }

    // 4. Invalid price on a new product.
    public function test_invalid_price_is_an_error(): void
    {
        $path = $this->writeCsv($this->header(), [$this->row(['sku' => 'SKU-0002', 'price' => 'abc'])]);

        $mapping = $this->service->analyze($path)['mapping'];
        $report = $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $this->assertSame(1, $report->errors);
        $this->assertDatabaseMissing('products', ['sku' => 'SKU-0002']);
    }

    // 5. Product creation.
    public function test_it_creates_a_new_product_with_all_fields(): void
    {
        $path = $this->writeCsv($this->header(), [
            $this->row([
                'sku' => 'SKU-0003',
                'name' => 'Creme test',
                'price' => '3000',
                'cost_price' => '1500',
                'category' => 'Soins du visage',
                'brand' => 'Ma Marque',
                'tags' => 'Bio,Hydratant',
                'stock' => '10',
            ]),
        ]);

        $mapping = $this->service->analyze($path)['mapping'];
        $report = $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $this->assertSame(1, $report->created);
        $product = Product::where('sku', 'SKU-0003')->firstOrFail();
        $this->assertSame('3000.00', $product->price);
        $this->assertSame('Ma Marque', $product->brand->name);
        $this->assertCount(2, $product->tags);
        $this->assertSame(10, $product->stocks()->where('store_id', $this->store->id)->first()->quantity_available);
    }

    // 6. Partial update by SKU + "absent column never overwrites" + "empty value never overwrites".
    public function test_updating_an_existing_sku_only_changes_provided_non_empty_fields(): void
    {
        $product = Product::factory()->create([
            'sku' => 'SKU-0004',
            'price' => 1000,
            'description' => 'Description originale',
            'category_id' => $this->category->id,
        ]);

        // Header has no "Description" column at all this time.
        $path = $this->writeCsv('Nom;SKU;Prix', ['Nom test;SKU-0004;2500']);

        $mapping = $this->service->analyze($path)['mapping'];
        $report = $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $this->assertSame(1, $report->updated);
        $product->refresh();
        $this->assertSame('2500.00', $product->price);
        $this->assertSame('Description originale', $product->description);
    }

    public function test_empty_value_in_a_present_column_does_not_overwrite_existing_data(): void
    {
        $product = Product::factory()->create([
            'sku' => 'SKU-0005',
            'price' => 1000,
            'short_description' => 'Résumé existant',
            'category_id' => $this->category->id,
        ]);

        $path = $this->writeCsv($this->header(), [
            $this->row(['sku' => 'SKU-0005', 'price' => '1000', 'short' => '']),
        ]);

        $mapping = $this->service->analyze($path)['mapping'];
        $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $product->refresh();
        $this->assertSame('Résumé existant', $product->short_description);
    }

    // 7. Duplicate SKU within the same CSV.
    public function test_duplicate_sku_within_the_same_csv_is_reported(): void
    {
        $path = $this->writeCsv($this->header(), [
            $this->row(['sku' => 'SKU-0006', 'name' => 'Premiere occurrence']),
            $this->row(['sku' => 'SKU-0006', 'name' => 'Deuxieme occurrence']),
        ]);

        $mapping = $this->service->analyze($path)['mapping'];
        $report = $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $this->assertSame(1, $report->created);
        $this->assertSame(1, $report->duplicates);
        $this->assertDatabaseHas('products', ['sku' => 'SKU-0006', 'name' => 'Premiere occurrence']);
    }

    // 8. Automatic category creation + count.
    public function test_unknown_category_is_created_automatically_and_counted(): void
    {
        $path = $this->writeCsv($this->header(), [
            $this->row(['sku' => 'SKU-0007', 'category' => 'Categorie Toute Neuve']),
        ]);

        $mapping = $this->service->analyze($path)['mapping'];
        $report = $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $this->assertSame(1, $report->categoriesCreated);
        $this->assertDatabaseHas('product_categories', ['name' => 'Categorie Toute Neuve']);
    }

    // 8b. Category + subcategory both new: subcategory gets parent_id, product attaches to the subcategory.
    public function test_new_category_and_new_subcategory_are_both_created_with_correct_parent_link(): void
    {
        $path = $this->writeCsv($this->header(), [
            $this->row(['sku' => 'SKU-SUBCAT-1', 'category' => 'Soins du corps', 'subcategory' => 'Huiles corps']),
        ]);

        $mapping = $this->service->analyze($path)['mapping'];
        $report = $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $this->assertSame(2, $report->categoriesCreated);

        $parent = ProductCategory::where('name', 'Soins du corps')->firstOrFail();
        $child = ProductCategory::where('name', 'Huiles corps')->firstOrFail();

        $this->assertNull($parent->parent_id);
        $this->assertSame($parent->id, $child->parent_id);

        $product = Product::where('sku', 'SKU-SUBCAT-1')->firstOrFail();
        $this->assertSame($child->id, $product->category_id);
    }

    // 8c. Root category already exists: only the subcategory is created, correctly linked.
    public function test_existing_category_with_new_subcategory_only_creates_the_subcategory(): void
    {
        $path = $this->writeCsv($this->header(), [
            $this->row(['sku' => 'SKU-SUBCAT-2', 'category' => 'Soins du visage', 'subcategory' => 'Sérums']),
        ]);

        $mapping = $this->service->analyze($path)['mapping'];
        $report = $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $this->assertSame(1, $report->categoriesCreated);

        $child = ProductCategory::where('name', 'Sérums')->firstOrFail();
        $this->assertSame($this->category->id, $child->parent_id);

        $product = Product::where('sku', 'SKU-SUBCAT-2')->firstOrFail();
        $this->assertSame($child->id, $product->category_id);
    }

    // 8d. Re-importing the same category/subcategory pair does not duplicate the subcategory.
    public function test_reimporting_the_same_subcategory_is_idempotent(): void
    {
        $path = $this->writeCsv($this->header(), [
            $this->row(['sku' => 'SKU-SUBCAT-3', 'category' => 'Soins du corps', 'subcategory' => 'Laits & crèmes corps']),
        ]);

        $mapping = $this->service->analyze($path)['mapping'];
        $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());
        $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $this->assertSame(1, ProductCategory::where('name', 'Laits & crèmes corps')->count());
    }

    // 8e. Non-regression: no subcategory column value => behaviour identical to before this feature.
    public function test_missing_subcategory_falls_back_to_the_root_category_unchanged(): void
    {
        $path = $this->writeCsv($this->header(), [
            $this->row(['sku' => 'SKU-SUBCAT-4', 'category' => 'Soins du visage', 'subcategory' => '']),
        ]);

        $mapping = $this->service->analyze($path)['mapping'];
        $report = $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $this->assertSame(0, $report->categoriesCreated);
        $product = Product::where('sku', 'SKU-SUBCAT-4')->firstOrFail();
        $this->assertSame($this->category->id, $product->category_id);
    }

    // 8f. analyze() maps the "Sous-catégorie" header instead of leaving it unmapped.
    public function test_analyze_maps_the_subcategory_column(): void
    {
        $path = $this->writeCsv($this->header(), [$this->row()]);

        $analysis = $this->service->analyze($path);

        $this->assertNotNull($analysis['mapping']['subcategory']);
        $this->assertNotContains($analysis['mapping']['subcategory'], $analysis['unmapped_header_indexes']);
    }

    // 9. Automatic brand creation + count.
    public function test_unknown_brand_is_created_automatically_and_counted(): void
    {
        $path = $this->writeCsv($this->header(), [
            $this->row(['sku' => 'SKU-0008', 'brand' => 'Marque Toute Neuve']),
        ]);

        $mapping = $this->service->analyze($path)['mapping'];
        $report = $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $this->assertSame(1, $report->brandsCreated);
        $this->assertDatabaseHas('brands', ['name' => 'Marque Toute Neuve']);
    }

    // 10. Multiple tags, mixing existing and new.
    public function test_multiple_tags_are_split_and_resolved(): void
    {
        Tag::factory()->create(['name' => 'Bio', 'slug' => 'bio']);

        $path = $this->writeCsv($this->header(), [
            $this->row(['sku' => 'SKU-0009', 'tags' => 'Bio, Tag Inconnu']),
        ]);

        $mapping = $this->service->analyze($path)['mapping'];
        $report = $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $this->assertSame(1, $report->tagsCreated);
        $product = Product::where('sku', 'SKU-0009')->firstOrFail();
        $this->assertSame(['Bio', 'Tag Inconnu'], $product->tags->pluck('name')->sort()->values()->all());
    }

    // 11. Initial stock scoped to the chosen store.
    public function test_initial_stock_is_scoped_to_the_selected_store(): void
    {
        $otherStore = Store::factory()->create();

        $path = $this->writeCsv($this->header(), [
            $this->row(['sku' => 'SKU-0010', 'stock' => '25']),
        ]);

        $mapping = $this->service->analyze($path)['mapping'];
        $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $product = Product::where('sku', 'SKU-0010')->firstOrFail();
        $this->assertSame(25, $product->stocks()->where('store_id', $this->store->id)->first()->quantity_available);
        $this->assertSame(0, $product->stocks()->where('store_id', $otherStore->id)->count());
    }

    // 12. Idempotent import (running twice doesn't duplicate anything).
    public function test_importing_the_same_file_twice_is_idempotent(): void
    {
        $path = $this->writeCsv($this->header(), [
            $this->row(['sku' => 'SKU-0011', 'category' => 'Cat Idempotente', 'brand' => 'Marque Idempotente', 'tags' => 'TagIdempotent', 'stock' => '5']),
        ]);

        $mapping = $this->service->analyze($path)['mapping'];
        $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());
        $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $this->assertSame(1, Product::where('sku', 'SKU-0011')->count());
        $this->assertSame(1, ProductCategory::where('name', 'Cat Idempotente')->count());
        $this->assertSame(1, Brand::where('name', 'Marque Idempotente')->count());
        $this->assertSame(1, Tag::where('name', 'TagIdempotent')->count());
        $product = Product::where('sku', 'SKU-0011')->firstOrFail();
        $this->assertSame(1, Stock::where('product_id', $product->id)->count());
    }

    // Stock replacement (never additive) + no duplicate stock rows.
    public function test_stock_from_csv_replaces_rather_than_adds_to_existing_stock(): void
    {
        $product = Product::factory()->create(['sku' => 'SKU-0012', 'category_id' => $this->category->id]);
        (new ProductService())->setInitialStock($product, $this->store, 10);

        $path = $this->writeCsv($this->header(), [
            $this->row(['sku' => 'SKU-0012', 'stock' => '4']),
        ]);

        $mapping = $this->service->analyze($path)['mapping'];
        $report = $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $this->assertSame(4, $product->stocks()->where('store_id', $this->store->id)->first()->quantity_available);
        $this->assertSame(1, $product->stocks()->where('store_id', $this->store->id)->count());
        $this->assertSame(10, $report->rows[0]->stockImpact['current']);
        $this->assertSame(4, $report->rows[0]->stockImpact['incoming']);
        $this->assertSame('replace', $report->rows[0]->stockImpact['action']);
    }

    public function test_empty_stock_column_on_update_leaves_stock_untouched(): void
    {
        $product = Product::factory()->create(['sku' => 'SKU-0013', 'category_id' => $this->category->id]);
        (new ProductService())->setInitialStock($product, $this->store, 7);

        $path = $this->writeCsv($this->header(), [
            $this->row(['sku' => 'SKU-0013', 'stock' => '']),
        ]);

        $mapping = $this->service->analyze($path)['mapping'];
        $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $this->assertSame(7, $product->stocks()->where('store_id', $this->store->id)->first()->quantity_available);
    }

    // 13. Error report as CSV.
    public function test_error_rows_can_be_exported_as_csv(): void
    {
        $path = $this->writeCsv($this->header(), [
            $this->row(['sku' => '']),
            $this->row(['sku' => 'SKU-0014']),
        ]);

        $mapping = $this->service->analyze($path)['mapping'];
        $report = $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $csv = $report->toErrorCsv();

        $this->assertStringContainsString('SKU manquant', $csv);
        $this->assertStringNotContainsString('SKU-0014', $csv);
    }

    // 14. Invalid CSV file.
    public function test_an_empty_file_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csvtest_');
        file_put_contents($path, '');

        $this->expectException(RuntimeException::class);

        $this->service->analyze($path);
    }

    // 15. Large file / chunking.
    public function test_a_large_file_is_fully_processed_across_multiple_chunks(): void
    {
        $lines = [];

        for ($i = 1; $i <= 250; $i++) {
            $lines[] = $this->row(['sku' => sprintf('SKU-BULK-%04d', $i), 'name' => 'Produit '.$i]);
        }

        $path = $this->writeCsv($this->header(), $lines);

        $mapping = $this->service->analyze($path)['mapping'];
        $report = $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $this->assertSame(250, $report->totalRows);
        $this->assertSame(250, $report->created);
        $this->assertSame(0, $report->errors);
        $this->assertSame(250, Product::count());
    }

    // Row-level rollback: a failing row must not corrupt other rows or leave a partial product.
    public function test_a_failing_row_does_not_affect_other_rows(): void
    {
        Product::factory()->create(['sku' => 'SKU-EXISTING-SLUG', 'slug' => 'produit-collision', 'category_id' => $this->category->id]);

        $path = $this->writeCsv($this->header(), [
            $this->row(['sku' => 'SKU-0015', 'name' => 'Produit Collision']), // will slugify to 'produit-collision', colliding
            $this->row(['sku' => 'SKU-0016', 'name' => 'Produit Valide']),
        ]);

        $mapping = $this->service->analyze($path)['mapping'];
        $report = $this->service->import($path, $mapping, $this->store, $this->defaultPolicies());

        $this->assertSame(ImportRowResult::STATUS_ERROR, $report->rows[0]->status);
        $this->assertDatabaseMissing('products', ['sku' => 'SKU-0015']);
        $this->assertSame(ImportRowResult::STATUS_NEW, $report->rows[1]->status);
        $this->assertDatabaseHas('products', ['sku' => 'SKU-0016']);
    }
}
