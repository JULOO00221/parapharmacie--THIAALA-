<?php

namespace Tests\Feature\ImageSourcing;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImageCandidate;
use App\Services\ImageSourcing\OpenBeautyFactsClient;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SourceProductImagesCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(app(StorageService::class)->disk());
        Http::preventStrayRequests();
        $this->app->singleton(OpenBeautyFactsClient::class, fn (): OpenBeautyFactsClient => new OpenBeautyFactsClient(
            static fn (float $seconds) => null,
        ));

        $this->csv = storage_path('framework/testing/sans-photo.csv');
    }

    protected function tearDown(): void
    {
        if (is_file($this->csv)) {
            unlink($this->csv);
        }

        parent::tearDown();
    }

    private function productWithoutImage(string $name, string $brand): Product
    {
        return Product::factory()->create([
            'name' => $name,
            'brand_id' => Brand::factory()->create(['name' => $brand])->id,
        ]);
    }

    public function test_it_only_looks_at_products_that_have_no_photo(): void
    {
        $withPhoto = $this->productWithoutImage('DOVE SAVON PAMPERING 90G', 'Dove');
        ProductImage::query()->create([
            'product_id' => $withPhoto->id,
            'path' => 'products/deja-la.webp',
            'is_primary' => true,
        ]);

        Http::fake(['*/cgi/search.pl*' => Http::response(['count' => 0, 'products' => []])]);

        $this->artisan('products:source-images', ['--limit' => 10, '--csv' => $this->csv])
            ->assertSuccessful();

        // Le seul produit du catalogue a déjà une photo : rien à chercher.
        Http::assertNothingSent();
        $this->assertFileDoesNotExist($this->csv);
    }

    public function test_dry_run_writes_nothing_at_all(): void
    {
        $this->productWithoutImage('MIXA CREME CICA REPAIR POT 400ML', 'Mixa');

        Http::fake(['*/cgi/search.pl*' => Http::response([
            'count' => 1,
            'products' => [[
                'code' => '3600550000000',
                'product_name' => 'Mixa Cica Repair Crème',
                'brands' => 'Mixa',
                'quantity' => '400ml',
                'image_front_url' => 'https://images.openbeautyfacts.org/x/front_fr.1.400.jpg',
                'images' => ['1' => ['uploader' => 'aminata'], 'front_fr' => ['imgid' => '1']],
            ]],
        ])]);

        $this->artisan('products:source-images', ['--limit' => 5, '--dry-run' => true, '--csv' => $this->csv])
            ->expectsOutputToContain('Simulation')
            ->assertSuccessful();

        $this->assertSame(0, ProductImageCandidate::count());
        $this->assertSame(0, ProductImage::count());
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'images.openbeautyfacts.org'));
    }

    /**
     * Le CSV sert à décider quelles marques photographier en boutique : il
     * doit donc dire, pour chaque produit, combien d'autres produits de la
     * même marque sont dans le même cas, et les marques les plus coûteuses
     * doivent arriver en premier.
     */
    public function test_the_csv_groups_unmatched_products_by_brand_largest_first(): void
    {
        foreach (['SAVON KARITE 200G', 'HUILE BAOBAB 100ML', 'LAIT CORPS 500ML'] as $name) {
            $this->productWithoutImage($name, 'Gorée Soins');
        }
        $this->productWithoutImage('GEL DOUCHE COCO 250ML', 'Dakar Beauté');

        Http::fake(['*/cgi/search.pl*' => Http::response(['count' => 0, 'products' => []])]);

        $this->artisan('products:source-images', ['--limit' => 10, '--csv' => $this->csv])
            ->assertSuccessful();

        $this->assertFileExists($this->csv);

        $rows = array_map('str_getcsv', array_filter(file($this->csv, FILE_IGNORE_NEW_LINES)));
        $header = array_shift($rows);

        $this->assertSame('marque', ltrim($header[0], "\u{FEFF}"));
        $this->assertSame('produits_sans_photo_pour_la_marque', $header[1]);
        $this->assertCount(4, $rows);

        // La marque qui coûte le plus de photos ouvre le fichier.
        $this->assertSame('Gorée Soins', $rows[0][0]);
        $this->assertSame('3', $rows[0][1]);
        $this->assertSame('Dakar Beauté', $rows[3][0]);
        $this->assertSame('1', $rows[3][1]);

        // La raison est explicite : « pas trouvé » et « source en panne » ne
        // se confondent pas.
        $this->assertSame('aucun_resultat', $rows[0][5]);
    }

    public function test_it_can_be_limited_to_one_brand(): void
    {
        $this->productWithoutImage('SAVON KARITE 200G', 'Gorée Soins');
        $this->productWithoutImage('GEL DOUCHE COCO 250ML', 'Dakar Beauté');

        Http::fake(['*/cgi/search.pl*' => Http::response(['count' => 0, 'products' => []])]);

        $this->artisan('products:source-images', [
            '--brand' => 'Gorée Soins',
            '--limit' => 10,
            '--csv' => $this->csv,
        ])->assertSuccessful();

        $rows = array_map('str_getcsv', array_filter(file($this->csv, FILE_IGNORE_NEW_LINES)));
        array_shift($rows);

        $this->assertCount(1, $rows);
        $this->assertSame('Gorée Soins', $rows[0][0]);
    }

    /**
     * Un produit sans correspondance ne laisse aucune trace en base. Sans
     * --offset, relancer la commande avec --limit retomberait indéfiniment
     * sur les mêmes premiers produits au lieu d'avancer dans le catalogue.
     */
    public function test_offset_moves_on_to_the_next_batch(): void
    {
        foreach (['PREMIER 100ML', 'DEUXIEME 100ML', 'TROISIEME 100ML'] as $name) {
            $this->productWithoutImage($name, 'Gorée Soins');
        }

        Http::fake(['*/cgi/search.pl*' => Http::response(['count' => 0, 'products' => []])]);

        $this->artisan('products:source-images', ['--limit' => 1, '--offset' => 2, '--csv' => $this->csv])
            ->assertSuccessful();

        $rows = array_map('str_getcsv', array_filter(file($this->csv, FILE_IGNORE_NEW_LINES)));
        array_shift($rows);

        $this->assertCount(1, $rows);
        $this->assertSame('TROISIEME 100ML', $rows[0][3]);
    }

    public function test_an_unreachable_source_is_written_as_such_not_as_a_missing_product(): void
    {
        $this->productWithoutImage('SAVON KARITE 200G', 'Gorée Soins');

        Http::fake(['*' => Http::response('', 503)]);

        $this->artisan('products:source-images', ['--limit' => 5, '--csv' => $this->csv])
            ->assertSuccessful();

        $rows = array_map('str_getcsv', array_filter(file($this->csv, FILE_IGNORE_NEW_LINES)));
        array_shift($rows);

        $this->assertSame('source_indisponible', $rows[0][5]);
    }
}
