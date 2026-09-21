<?php

namespace Tests\Feature\ImageSourcing;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImageCandidate;
use App\Services\ImageSourcing\OpenBeautyFactsClient;
use App\Services\ImageSourcing\ProductImageSourcingService;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Garantie centrale de cette fonctionnalité : la recherche automatique ne
 * publie jamais d'image. Elle ne produit que des propositions, invisibles de
 * la boutique tant qu'un humain ne les a pas validées.
 */
class ProductImageSourcingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(app(StorageService::class)->disk());
        // Aucun test ne doit sortir sur le réseau : tout appel non simulé
        // doit échouer bruyamment plutôt que partir chez Open Beauty Facts.
        Http::preventStrayRequests();
        // Les pauses qui respectent la limite de débit de la source n'ont pas
        // de sens face à des réponses simulées : sans cela, la suite passerait
        // l'essentiel de son temps à dormir.
        $this->app->singleton(OpenBeautyFactsClient::class, fn (): OpenBeautyFactsClient => new OpenBeautyFactsClient(
            static fn (float $seconds) => null,
        ));
    }

    private function product(string $name = 'MIXA CREME CICA REPAIR POT 400ML', string $brand = 'Mixa'): Product
    {
        return Product::factory()->create([
            'name' => $name,
            'brand_id' => Brand::factory()->create(['name' => $brand])->id,
            'barcode' => null,
        ]);
    }

    /** Une réponse de recherche contenant un seul produit, avec sa photo. */
    private function fakeSearch(string $productName, string $brands, string $quantity = '400ml'): void
    {
        Http::fake([
            '*/cgi/search.pl*' => Http::response([
                'count' => 1,
                'products' => [[
                    'code' => '3600550000000',
                    'product_name' => $productName,
                    'brands' => $brands,
                    'quantity' => $quantity,
                    'image_front_url' => 'https://images.openbeautyfacts.org/images/products/360/055/000/0000/front_fr.12.400.jpg',
                    'images' => [
                        '3' => ['uploader' => 'aminata'],
                        'front_fr' => ['imgid' => '3', 'rev' => '12'],
                    ],
                ]],
            ]),
            'images.openbeautyfacts.org/*' => Http::response($this->jpegBinary(), headers: ['Content-Type' => 'image/jpeg']),
        ]);
    }

    /** Un JPEG réel, par défaut 1200 px de large, pour éprouver le redimensionnement. */
    private function jpegBinary(int $width = 1200, int $height = 1200): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 210, 90, 40));
        ob_start();
        imagejpeg($image, null, 92);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    public function test_it_records_a_candidate_and_never_touches_the_product_images(): void
    {
        $product = $this->product();
        $this->fakeSearch('Mixa Cica Repair Crème', 'Mixa');

        $outcome = app(ProductImageSourcingService::class)->sourceFor($product, dryRun: false, minConfidence: 55);

        $this->assertTrue($outcome->isMatch());
        $this->assertSame(0, ProductImage::count(), 'Aucune image ne doit être publiée automatiquement.');

        $candidate = ProductImageCandidate::sole();
        $this->assertSame(ProductImageCandidate::STATUS_PENDING, $candidate->status);
        $this->assertSame($product->id, $candidate->product_id);
        $this->assertSame('CC-BY-SA-3.0', $candidate->license_code);
        $this->assertSame('Photo : aminata — Open Beauty Facts (CC-BY-SA-3.0)', $candidate->attribution);
        $this->assertStringContainsString('openbeautyfacts.org', (string) $candidate->source_page_url);
    }

    /**
     * Une fiche porte souvent une photo de face par langue, déposées par des
     * contributeurs différents. L'attribution doit citer l'auteur de la photo
     * que l'on publie, pas celui d'une autre : créditer la mauvaise personne
     * ne respecte pas la licence.
     */
    public function test_the_attribution_credits_the_author_of_the_photo_actually_used(): void
    {
        Http::fake([
            '*/cgi/search.pl*' => Http::response([
                'count' => 1,
                'products' => [[
                    'code' => '3600550000000',
                    'product_name' => 'Mixa Cica Repair Crème',
                    'brands' => 'Mixa',
                    'quantity' => '400ml',
                    // C'est la photo française qui est retenue…
                    'image_front_url' => 'https://images.openbeautyfacts.org/x/front_fr.12.400.jpg',
                    'images' => [
                        '1' => ['uploader' => 'contributeur-anglais'],
                        '3' => ['uploader' => 'contributrice-francaise'],
                        // … alors que la fiche anglaise vient en premier.
                        'front_en' => ['imgid' => '1', 'rev' => '9'],
                        'front_fr' => ['imgid' => '3', 'rev' => '12'],
                    ],
                ]],
            ]),
            'images.openbeautyfacts.org/*' => Http::response($this->jpegBinary()),
        ]);

        app(ProductImageSourcingService::class)->sourceFor($this->product(), dryRun: false, minConfidence: 55);

        $this->assertSame(
            'Photo : contributrice-francaise — Open Beauty Facts (CC-BY-SA-3.0)',
            ProductImageCandidate::sole()->attribution,
        );
    }

    public function test_an_unknown_photographer_falls_back_to_crediting_the_community(): void
    {
        Http::fake([
            '*/cgi/search.pl*' => Http::response([
                'count' => 1,
                'products' => [[
                    'code' => '3600550000000',
                    'product_name' => 'Mixa Cica Repair Crème',
                    'brands' => 'Mixa',
                    'quantity' => '400ml',
                    'image_front_url' => 'https://images.openbeautyfacts.org/x/front_fr.12.400.jpg',
                    'images' => [],
                ]],
            ]),
            'images.openbeautyfacts.org/*' => Http::response($this->jpegBinary()),
        ]);

        app(ProductImageSourcingService::class)->sourceFor($this->product(), dryRun: false, minConfidence: 55);

        $this->assertSame(
            'Photo : les contributeurs Open Beauty Facts — Open Beauty Facts (CC-BY-SA-3.0)',
            ProductImageCandidate::sole()->attribution,
        );
    }

    public function test_the_downloaded_image_is_resized_converted_and_kept_under_budget(): void
    {
        $this->fakeSearch('Mixa Cica Repair Crème', 'Mixa');

        app(ProductImageSourcingService::class)->sourceFor($this->product(), dryRun: false, minConfidence: 55);

        $candidate = ProductImageCandidate::sole();
        $disk = Storage::disk(app(StorageService::class)->disk());

        $this->assertTrue($disk->exists((string) $candidate->path));
        $this->assertStringEndsWith('.webp', (string) $candidate->path);
        $this->assertSame(800, $candidate->width);
        $this->assertLessThanOrEqual(80 * 1024, (int) $candidate->bytes);

        // Le fichier est bien du WebP, pas un JPEG renommé.
        $this->assertSame('WEBP', substr((string) $disk->get((string) $candidate->path), 8, 4));
    }

    public function test_the_full_resolution_photo_is_requested_rather_than_the_thumbnail(): void
    {
        $this->fakeSearch('Mixa Cica Repair Crème', 'Mixa');

        app(ProductImageSourcingService::class)->sourceFor($this->product(), dryRun: false, minConfidence: 55);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'front_fr.12.full.jpg'));
    }

    public function test_every_request_identifies_the_application(): void
    {
        $this->fakeSearch('Mixa Cica Repair Crème', 'Mixa');

        app(ProductImageSourcingService::class)->sourceFor($this->product(), dryRun: false, minConfidence: 55);

        Http::assertSent(function (Request $request): bool {
            $agent = $request->header('User-Agent')[0] ?? '';

            return str_contains($agent, 'TambacoundaCosmetix') && str_contains($agent, '@');
        });
    }

    public function test_dry_run_downloads_nothing_and_writes_nothing(): void
    {
        $this->fakeSearch('Mixa Cica Repair Crème', 'Mixa');

        $outcome = app(ProductImageSourcingService::class)->sourceFor($this->product(), dryRun: true, minConfidence: 55);

        $this->assertTrue($outcome->isMatch());
        $this->assertSame(0, ProductImageCandidate::count());
        $this->assertSame(0, ProductImage::count());
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'images.openbeautyfacts.org'));
    }

    public function test_a_weak_match_is_reported_instead_of_being_proposed(): void
    {
        $product = $this->product('THIAALA SAVON KARITE ARTISANAL 200G', 'Gorée Soins');
        $this->fakeSearch('Dentifrice blancheur menthe', 'Signal', '75ml');

        $outcome = app(ProductImageSourcingService::class)->sourceFor($product, dryRun: false, minConfidence: 55);

        $this->assertSame('score_insuffisant', $outcome->status);
        $this->assertSame(0, ProductImageCandidate::count());
    }

    /**
     * Le catalogue vend des parfums génériques nommés d'après le parfum
     * qu'ils imitent. Sans garde-fou sur la marque, ils décrocheraient la
     * photo du parfum original — une photo légalement et commercialement
     * fausse.
     */
    public function test_a_different_brand_prevents_a_match_however_close_the_names(): void
    {
        $product = $this->product('IAP EDP (D) Nø69 ONE MILION (MINI) 50ML', 'IAP (parfums génériques)');
        $this->fakeSearch('One Million Eau de Parfum', 'Paco Rabanne', '50ml');

        $outcome = app(ProductImageSourcingService::class)->sourceFor($product, dryRun: false, minConfidence: 55);

        $this->assertSame('score_insuffisant', $outcome->status);
        $this->assertSame(0, ProductImageCandidate::count());
    }

    /**
     * Le défaut découvert sur le premier lot réel : les fiches d'Open Beauty
     * Facts sont souvent nommées d'après leur seule marque (« Signal »,
     * « Pierre Fabre »). Elles ressemblaient alors à tous les produits de la
     * marque à la fois et franchissaient le seuil sans partager un seul mot
     * avec le produit du catalogue — c'est ainsi qu'un Signal adulte avait
     * été proposé pour un Signal Kids.
     */
    public function test_a_remote_page_named_after_its_brand_alone_is_not_a_match(): void
    {
        $product = $this->product('SIGNAL KIDS 0-6ANS 70ML', 'Signal');
        $this->fakeSearch('Signal', 'Signal', '70ml');

        $outcome = app(ProductImageSourcingService::class)->sourceFor($product, dryRun: false, minConfidence: 55);

        $this->assertSame('score_insuffisant', $outcome->status);
        $this->assertSame(0, ProductImageCandidate::count());
    }

    public function test_one_word_in_common_is_enough_to_consider_a_match(): void
    {
        $product = $this->product('SIGNAL KIDS 0-6ANS 70ML', 'Signal');
        $this->fakeSearch('Signal Kids dentifrice fraise', 'Signal', '70ml');

        $outcome = app(ProductImageSourcingService::class)->sourceFor($product, dryRun: false, minConfidence: 55);

        $this->assertTrue($outcome->isMatch());
    }

    /**
     * Les contributeurs photographient parfois la tranche d'une boîte : très
     * allongée, l'image ne montre rien d'utilisable dans une vignette carrée.
     */
    public function test_a_banner_shaped_image_is_refused(): void
    {
        Http::fake([
            '*/cgi/search.pl*' => Http::response([
                'count' => 1,
                'products' => [[
                    'code' => '3600550000000',
                    'product_name' => 'Mixa Cica Repair Crème',
                    'brands' => 'Mixa',
                    'quantity' => '400ml',
                    'image_front_url' => 'https://images.openbeautyfacts.org/x/front_fr.1.400.jpg',
                    'images' => ['1' => ['uploader' => 'aminata'], 'front_fr' => ['imgid' => '1']],
                ]],
            ]),
            // Une image en bandeau : la tranche d'une boîte, pas le produit.
            'images.openbeautyfacts.org/*' => Http::response($this->jpegBinary(1600, 300)),
        ]);

        $outcome = app(ProductImageSourcingService::class)->sourceFor($this->product(), dryRun: false, minConfidence: 55);

        $this->assertSame('image_inutilisable', $outcome->status);
        $this->assertSame(0, ProductImageCandidate::count());
    }

    public function test_a_photo_already_rejected_is_not_proposed_again(): void
    {
        $product = $this->product();
        $this->fakeSearch('Mixa Cica Repair Crème', 'Mixa');

        $service = app(ProductImageSourcingService::class);
        $service->sourceFor($product, dryRun: false, minConfidence: 55);

        ProductImageCandidate::sole()->forceFill([
            'status' => ProductImageCandidate::STATUS_REJECTED,
            'path' => null,
        ])->save();

        $outcome = $service->sourceFor($product->fresh(), dryRun: false, minConfidence: 55);

        $this->assertSame('deja_rejete', $outcome->status);
        $this->assertSame(1, ProductImageCandidate::count());
    }

    public function test_a_pending_proposal_is_left_untouched_on_a_second_run(): void
    {
        $product = $this->product();
        $this->fakeSearch('Mixa Cica Repair Crème', 'Mixa');

        $service = app(ProductImageSourcingService::class);
        $service->sourceFor($product, dryRun: false, minConfidence: 55);
        $first = ProductImageCandidate::sole();

        $outcome = $service->sourceFor($product->fresh(), dryRun: false, minConfidence: 55);

        $this->assertSame('deja_en_attente', $outcome->status);
        $this->assertSame(1, ProductImageCandidate::count());
        $this->assertEquals($first->updated_at, ProductImageCandidate::sole()->updated_at);
    }

    public function test_an_internal_reference_is_never_searched_as_a_barcode(): void
    {
        $product = $this->product();
        // Neuf chiffres : une référence du logiciel de caisse, pas un GTIN.
        $product->forceFill(['barcode' => '000032536'])->save();
        $this->fakeSearch('Mixa Cica Repair Crème', 'Mixa');

        app(ProductImageSourcingService::class)->sourceFor($product, dryRun: false, minConfidence: 55);

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/api/v2/product/'));
    }

    public function test_a_valid_barcode_is_searched_directly(): void
    {
        $product = $this->product();
        $product->forceFill(['barcode' => '3017620422003'])->save();

        Http::fake([
            '*/api/v2/product/3017620422003.json*' => Http::response([
                'status' => 1,
                'product' => [
                    'code' => '3017620422003',
                    'product_name' => 'Mixa Cica Repair',
                    'brands' => 'Mixa',
                    'quantity' => '400ml',
                    'image_front_url' => 'https://images.openbeautyfacts.org/images/products/301/762/042/2003/front_fr.4.400.jpg',
                    'images' => ['2' => ['uploader' => 'moussa'], 'front_fr' => ['imgid' => '2']],
                ],
            ]),
            'images.openbeautyfacts.org/*' => Http::response($this->jpegBinary()),
        ]);

        $outcome = app(ProductImageSourcingService::class)->sourceFor($product, dryRun: false, minConfidence: 55);

        $this->assertTrue($outcome->isMatch());
        $this->assertSame(100, $outcome->confidence);
        $this->assertSame('barcode', ProductImageCandidate::sole()->match_method);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'search.pl'));
    }

    /**
     * Une source injoignable ne doit pas se confondre avec un produit
     * réellement introuvable : le premier cas se rejoue, le second appelle
     * une photo prise en boutique.
     */
    public function test_an_unreachable_source_is_reported_as_such(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        $outcome = app(ProductImageSourcingService::class)->sourceFor($this->product(), dryRun: false, minConfidence: 55);

        $this->assertSame('source_indisponible', $outcome->status);
        $this->assertTrue($outcome->isRetryable());
        $this->assertSame(0, ProductImageCandidate::count());
    }

    public function test_a_source_with_no_result_is_reported_separately(): void
    {
        Http::fake(['*/cgi/search.pl*' => Http::response(['count' => 0, 'products' => []])]);

        $outcome = app(ProductImageSourcingService::class)->sourceFor($this->product(), dryRun: false, minConfidence: 55);

        $this->assertSame('aucun_resultat', $outcome->status);
        $this->assertFalse($outcome->isRetryable());
    }
}
