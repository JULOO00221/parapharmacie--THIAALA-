<?php

namespace Tests\Feature\Catalog;

use App\Filament\Resources\Products\RelationManagers\ImagesRelationManager;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\ProductService;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductImageStorageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::findOrCreate('admin', 'web'));

        $category = ProductCategory::factory()->create();
        $this->product = Product::factory()->create(['category_id' => $category->id]);
    }

    // --- ProductService::removeImage() ---------------------------------

    public function test_remove_image_deletes_the_physical_file_and_the_record(): void
    {
        $path = Storage::disk('public')->put('products', UploadedFile::fake()->image('photo.jpg'));
        $image = $this->product->images()->create(['path' => $path, 'is_primary' => true]);

        app(ProductService::class)->removeImage($image);

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    }

    public function test_remove_image_succeeds_even_when_the_physical_file_is_already_missing(): void
    {
        $image = $this->product->images()->create(['path' => 'products/does-not-exist.jpg', 'is_primary' => true]);

        app(ProductService::class)->removeImage($image);

        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    }

    // --- Real upload through the Filament relation manager -------------

    public function test_admin_can_upload_a_real_image_through_the_relation_manager(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ImagesRelationManager::class, [
            'ownerRecord' => $this->product,
            'pageClass' => \App\Filament\Resources\Products\Pages\EditProduct::class,
        ])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->image('savon.jpg', 400, 400),
                'alt_text' => 'Savon noir traditionnel',
                'is_primary' => true,
            ])
            ->assertHasNoActionErrors();

        $image = ProductImage::where('product_id', $this->product->id)->firstOrFail();

        Storage::disk('public')->assertExists($image->path);
        $this->assertTrue($image->is_primary);
        $this->assertSame('Savon noir traditionnel', $image->alt_text);
    }

    public function test_relation_manager_rejects_a_non_image_file(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ImagesRelationManager::class, [
            'ownerRecord' => $this->product,
            'pageClass' => \App\Filament\Resources\Products\Pages\EditProduct::class,
        ])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->create('malicious.svg', 10, 'image/svg+xml'),
            ])
            ->assertHasActionErrors(['path']);

        $this->assertSame(0, ProductImage::where('product_id', $this->product->id)->count());
    }

    public function test_relation_manager_rejects_a_file_larger_than_the_limit(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ImagesRelationManager::class, [
            'ownerRecord' => $this->product,
            'pageClass' => \App\Filament\Resources\Products\Pages\EditProduct::class,
        ])
            ->callTableAction('create', data: [
                // maxSize is in kilobytes (4096 = 4 MB); this fake file is 5 MB.
                'path' => UploadedFile::fake()->image('trop-gros.jpg')->size(5000),
            ])
            ->assertHasActionErrors(['path']);

        $this->assertSame(0, ProductImage::where('product_id', $this->product->id)->count());
    }

    public function test_deleting_an_image_through_the_relation_manager_removes_the_physical_file(): void
    {
        $this->actingAs($this->admin);

        $path = Storage::disk('public')->put('products', UploadedFile::fake()->image('a-supprimer.jpg'));
        $image = $this->product->images()->create(['path' => $path, 'is_primary' => false]);

        Livewire::test(ImagesRelationManager::class, [
            'ownerRecord' => $this->product,
            'pageClass' => \App\Filament\Resources\Products\Pages\EditProduct::class,
        ])->callTableAction('delete', $image);

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    }

    public function test_update_image_replaces_the_file_and_deletes_the_old_one_only_after_success(): void
    {
        $originalPath = Storage::disk('public')->put('products', UploadedFile::fake()->image('original.jpg'));
        $image = $this->product->images()->create(['path' => $originalPath, 'is_primary' => true]);

        $newPath = Storage::disk('public')->put('products', UploadedFile::fake()->image('remplacement.jpg'));

        $updated = app(ProductService::class)->updateImage($image, [
            'path' => $newPath,
            'alt_text' => 'Nouvelle photo',
        ]);

        Storage::disk('public')->assertMissing($originalPath);
        Storage::disk('public')->assertExists($newPath);
        $this->assertSame($newPath, $updated->path);
        $this->assertSame('Nouvelle photo', $updated->alt_text);
    }

    public function test_update_image_never_touches_the_file_when_the_database_update_fails(): void
    {
        $originalPath = Storage::disk('public')->put('products', UploadedFile::fake()->image('original.jpg'));
        $image = $this->product->images()->create(['path' => $originalPath, 'is_primary' => true]);
        $newPath = Storage::disk('public')->put('products', UploadedFile::fake()->image('remplacement.jpg'));

        // alt_text longer than the column allows makes the update throw,
        // simulating a failed persistence step.
        try {
            app(ProductService::class)->updateImage($image, [
                'path' => $newPath,
                'alt_text' => str_repeat('a', 1000),
            ]);
            $this->fail('Expected the update to fail.');
        } catch (\Throwable) {
            // expected
        }

        // The old file must still be there — nothing was deleted prematurely.
        Storage::disk('public')->assertExists($originalPath);
        $this->assertSame($originalPath, $image->fresh()->path);
    }

    public function test_update_image_leaves_the_file_untouched_when_only_metadata_changes(): void
    {
        $path = Storage::disk('public')->put('products', UploadedFile::fake()->image('inchangee.jpg'));
        $image = $this->product->images()->create(['path' => $path, 'is_primary' => false]);

        app(ProductService::class)->updateImage($image, ['alt_text' => 'Juste le texte alternatif']);

        Storage::disk('public')->assertExists($path);
        $this->assertSame($path, $image->fresh()->path);
    }

    // --- API never exposes the raw path ---------------------------------

    public function test_product_image_api_resource_never_exposes_the_raw_path(): void
    {
        $path = Storage::disk('public')->put('products', UploadedFile::fake()->image('visible.jpg'));
        $this->product->update(['is_active' => true]);
        $this->product->images()->create(['path' => $path, 'is_primary' => true, 'alt_text' => 'Visible']);

        $response = $this->getJson('/api/v1/products/'.$this->product->slug);

        $response->assertOk();

        $this->assertStringNotContainsString('"path"', json_encode($response->json()));
        $response->assertJsonPath('data.primary_image.url', app(StorageService::class)->url($path));
    }
}
