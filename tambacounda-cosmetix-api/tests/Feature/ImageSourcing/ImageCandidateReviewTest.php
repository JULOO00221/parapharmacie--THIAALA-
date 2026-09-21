<?php

namespace Tests\Feature\ImageSourcing;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImageCandidate;
use App\Models\User;
use App\Services\ImageSourcing\ImageCandidateReviewer;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * La validation est le seul moment où une photo trouvée automatiquement
 * devient une photo publiée. Elle doit donc être transactionnelle et
 * idempotente, comme toute écriture métier du projet.
 */
class ImageCandidateReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(app(StorageService::class)->disk());
    }

    private function candidate(array $attributes = []): ProductImageCandidate
    {
        $product = Product::factory()->create(['name' => 'Crème hydratante karité']);
        $path = 'product-image-candidates/exemple.webp';

        Storage::disk(app(StorageService::class)->disk())->put($path, 'contenu-webp');

        return ProductImageCandidate::query()->create(array_merge([
            'product_id' => $product->id,
            'source' => 'open_beauty_facts',
            'source_code' => '3600550000000',
            'source_image_url' => 'https://images.openbeautyfacts.org/exemple.full.jpg',
            'source_page_url' => 'https://world.openbeautyfacts.org/product/3600550000000',
            'source_product_name' => 'Crème karité',
            'license_code' => 'CC-BY-SA-3.0',
            'license_url' => 'https://creativecommons.org/licenses/by-sa/3.0/deed.fr',
            'attribution' => 'Photo : aminata — Open Beauty Facts (CC-BY-SA-3.0)',
            'match_method' => ProductImageCandidate::METHOD_BRAND_NAME,
            'confidence' => 88,
            'path' => $path,
            'width' => 800,
            'height' => 800,
            'bytes' => 42_000,
            'status' => ProductImageCandidate::STATUS_PENDING,
        ], $attributes));
    }

    public function test_approving_publishes_the_photo_with_its_attribution(): void
    {
        $candidate = $this->candidate();

        $image = app(ImageCandidateReviewer::class)->approve($candidate, User::factory()->create());

        $this->assertSame($candidate->product_id, $image->product_id);
        $this->assertTrue($image->is_primary, 'La première photo d\'un produit devient la principale.');
        $this->assertSame(0, $image->sort_order);
        $this->assertSame('Crème hydratante karité', $image->alt_text);
        $this->assertSame('Photo : aminata — Open Beauty Facts (CC-BY-SA-3.0)', $image->attribution);
        $this->assertSame('CC-BY-SA-3.0', $image->license_code);
        $this->assertStringContainsString('openbeautyfacts.org', (string) $image->source_url);

        // La photo publiée est une copie : rejeter plus tard une autre
        // proposition ne doit pas pouvoir effacer un fichier déjà en ligne.
        $this->assertNotSame($candidate->path, $image->path);
        Storage::disk(app(StorageService::class)->disk())->assertExists($image->path);

        $candidate->refresh();
        $this->assertSame(ProductImageCandidate::STATUS_APPROVED, $candidate->status);
        $this->assertNotNull($candidate->reviewed_at);
        $this->assertSame($image->id, $candidate->product_image_id);
    }

    public function test_approving_twice_does_not_publish_two_photos(): void
    {
        $candidate = $this->candidate();
        $reviewer = User::factory()->create();
        $service = app(ImageCandidateReviewer::class);

        $first = $service->approve($candidate, $reviewer);
        $second = $service->approve($candidate->fresh(), $reviewer);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ProductImage::count());
    }

    public function test_approving_one_photo_closes_the_other_proposals_for_that_product(): void
    {
        $candidate = $this->candidate();
        $other = $this->candidate([
            'product_id' => $candidate->product_id,
            'source_image_url' => 'https://images.openbeautyfacts.org/autre.full.jpg',
        ]);

        app(ImageCandidateReviewer::class)->approve($candidate, User::factory()->create());

        $this->assertSame(ProductImageCandidate::STATUS_REJECTED, $other->fresh()->status);
        $this->assertSame(1, ProductImage::count());
    }

    public function test_a_second_photo_does_not_steal_the_primary_slot(): void
    {
        $candidate = $this->candidate();
        ProductImage::query()->create([
            'product_id' => $candidate->product_id,
            'path' => 'products/photo-maison.webp',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $image = app(ImageCandidateReviewer::class)->approve($candidate, User::factory()->create());

        $this->assertFalse($image->is_primary);
        $this->assertSame(1, $image->sort_order);
    }

    /**
     * Une proposition issue d'un --dry-run n'a pas de fichier : la valider
     * créerait une image pointant dans le vide.
     */
    public function test_a_proposal_without_a_downloaded_file_cannot_be_approved(): void
    {
        $candidate = $this->candidate(['path' => null]);

        $this->expectException(RuntimeException::class);

        try {
            app(ImageCandidateReviewer::class)->approve($candidate, User::factory()->create());
        } finally {
            $this->assertSame(0, ProductImage::count());
        }
    }

    public function test_rejecting_keeps_the_decision_and_frees_the_file(): void
    {
        $candidate = $this->candidate();
        $path = (string) $candidate->path;

        app(ImageCandidateReviewer::class)->reject($candidate, User::factory()->create(), 'Produit différent.');

        $candidate->refresh();
        $this->assertSame(ProductImageCandidate::STATUS_REJECTED, $candidate->status);
        $this->assertSame('Produit différent.', $candidate->review_note);
        $this->assertNull($candidate->path);
        // L'URL d'origine reste : c'est elle qui empêche de reproposer la photo.
        $this->assertNotNull($candidate->source_image_url);
        Storage::disk(app(StorageService::class)->disk())->assertMissing($path);
        $this->assertSame(0, ProductImage::count());
    }

    public function test_rejecting_an_already_reviewed_proposal_changes_nothing(): void
    {
        $candidate = $this->candidate();
        $reviewer = User::factory()->create();
        app(ImageCandidateReviewer::class)->approve($candidate, $reviewer);

        app(ImageCandidateReviewer::class)->reject($candidate->fresh(), $reviewer, 'Trop tard.');

        $this->assertSame(ProductImageCandidate::STATUS_APPROVED, $candidate->fresh()->status);
        $this->assertSame(1, ProductImage::count());
    }
}
