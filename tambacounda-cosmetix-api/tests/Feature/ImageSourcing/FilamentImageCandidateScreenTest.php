<?php

namespace Tests\Feature\ImageSourcing;

use App\Filament\Resources\ProductImageCandidates\Pages\ListProductImageCandidates;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImageCandidate;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * L'écran de validation est le point de passage obligé entre la recherche
 * automatique et la boutique. Ces tests vérifient qu'il montre bien le produit
 * et la photo côte à côte, et que ses deux actions font ce qu'elles annoncent.
 */
class FilamentImageCandidateScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(app(StorageService::class)->disk());

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($this->admin);
    }

    private function candidate(): ProductImageCandidate
    {
        $product = Product::factory()->create(['name' => 'Crème hydratante karité']);
        $path = 'product-image-candidates/exemple.webp';
        Storage::disk(app(StorageService::class)->disk())->put($path, 'contenu-webp');

        return ProductImageCandidate::query()->create([
            'product_id' => $product->id,
            'source_image_url' => 'https://images.openbeautyfacts.org/exemple.full.jpg',
            'source_page_url' => 'https://world.openbeautyfacts.org/product/3600550000000',
            'source_product_name' => 'Crème au karité',
            'source_brand' => 'Gorée Soins',
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
        ]);
    }

    public function test_the_screen_shows_the_catalogue_product_beside_the_photo_found(): void
    {
        $candidate = $this->candidate();

        $this->get('/admin/product-image-candidates')
            ->assertSuccessful()
            ->assertSee('Crème hydratante karité')   // le produit du catalogue
            ->assertSee('Crème au karité')           // la fiche distante
            ->assertSee('88/100');                   // le score
    }

    public function test_approving_from_the_screen_publishes_the_photo(): void
    {
        $candidate = $this->candidate();

        Livewire::test(ListProductImageCandidates::class)
            ->callTableAction('approve', $candidate)
            ->assertHasNoTableActionErrors();

        $this->assertSame(ProductImageCandidate::STATUS_APPROVED, $candidate->fresh()->status);

        $image = ProductImage::sole();
        $this->assertSame($candidate->product_id, $image->product_id);
        $this->assertSame('Photo : aminata — Open Beauty Facts (CC-BY-SA-3.0)', $image->attribution);
        $this->assertTrue($image->requiresAttribution());
    }

    public function test_rejecting_from_the_screen_publishes_nothing(): void
    {
        $candidate = $this->candidate();

        Livewire::test(ListProductImageCandidates::class)
            ->callTableAction('reject', $candidate, ['note' => 'Ce n\'est pas le bon produit.'])
            ->assertHasNoTableActionErrors();

        $candidate->refresh();
        $this->assertSame(ProductImageCandidate::STATUS_REJECTED, $candidate->status);
        $this->assertSame('Ce n\'est pas le bon produit.', $candidate->review_note);
        $this->assertSame(0, ProductImage::count());
    }

    /** Les propositions viennent de la commande : on ne doit pas pouvoir en saisir à la main. */
    public function test_proposals_cannot_be_created_by_hand(): void
    {
        $this->get('/admin/product-image-candidates/create')->assertNotFound();
    }
}
