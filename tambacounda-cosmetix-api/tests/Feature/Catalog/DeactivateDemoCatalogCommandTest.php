<?php

namespace Tests\Feature\Catalog;

use App\Jobs\RevalidateFrontendCatalog;
use App\Models\Brand;
use App\Models\Product;
use Database\Seeders\BrandSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * La commande retire du site le catalogue de démonstration de la phase 2.
 * Deux garanties comptent : elle ne touche jamais aux produits réellement
 * vendus, et elle ne supprime rien — plusieurs produits de démonstration sont
 * référencés par des commandes passées.
 */
class DeactivateDemoCatalogCommandTest extends TestCase
{
    use RefreshDatabase;

    private function demoProduct(string $sku = 'TC-0001', string $brand = 'Baobab Soins'): Product
    {
        return Product::factory()->create([
            'sku' => $sku,
            'name' => 'Crème hydratante karité intense',
            'brand_id' => Brand::factory()->create(['name' => $brand, 'is_active' => true])->id,
            'is_active' => true,
        ]);
    }

    private function importedProduct(): Product
    {
        return Product::factory()->create([
            'sku' => 'THIAALA-000013',
            'name' => 'NIVEA DEMAQILLANT DOUX 125ML',
            'brand_id' => Brand::factory()->create(['name' => 'Nivea', 'is_active' => true])->id,
            'is_active' => true,
        ]);
    }

    public function test_it_hides_demo_products_and_brands_from_the_storefront(): void
    {
        $demo = $this->demoProduct();
        $imported = $this->importedProduct();

        $this->artisan('catalog:deactivate-demo')->assertSuccessful();

        $this->assertFalse($demo->fresh()->is_active);
        $this->assertFalse(Brand::whereKey($demo->brand_id)->sole()->is_active);

        $this->assertTrue($imported->fresh()->is_active, 'Un produit réellement vendu ne doit jamais être touché.');
        $this->assertTrue(Brand::whereKey($imported->brand_id)->sole()->is_active);
    }

    public function test_it_deletes_nothing(): void
    {
        $demo = $this->demoProduct();

        $this->artisan('catalog:deactivate-demo')->assertSuccessful();

        // Ni suppression, ni suppression douce : l'historique des commandes
        // qui référencent ces produits doit rester lisible.
        $this->assertDatabaseHas('products', ['id' => $demo->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('brands', ['id' => $demo->brand_id]);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $demo = $this->demoProduct();

        $this->artisan('catalog:deactivate-demo', ['--dry-run' => true])
            ->expectsOutputToContain('Simulation')
            ->assertSuccessful();

        $this->assertTrue($demo->fresh()->is_active);
        $this->assertTrue(Brand::whereKey($demo->brand_id)->sole()->is_active);
    }

    /**
     * L'observer du cache écoute les modèles, pas la base : sans passage par
     * Eloquent, le site continuerait à servir les produits retirés jusqu'à
     * expiration de son cache.
     */
    public function test_it_asks_the_storefront_to_drop_its_cache(): void
    {
        Queue::fake();
        $this->demoProduct();

        $this->artisan('catalog:deactivate-demo')->assertSuccessful();

        Queue::assertPushed(RevalidateFrontendCatalog::class);
    }

    public function test_running_it_twice_does_nothing_the_second_time(): void
    {
        $this->demoProduct();
        $this->artisan('catalog:deactivate-demo')->assertSuccessful();

        Queue::fake();

        $this->artisan('catalog:deactivate-demo')
            ->expectsOutputToContain('déjà retiré du site')
            ->assertSuccessful();

        // Rien n'a changé d'état : inutile de redemander un vidage du cache.
        Queue::assertNothingPushed();
    }

    public function test_reactivate_puts_the_demo_catalogue_back(): void
    {
        $demo = $this->demoProduct();
        $this->artisan('catalog:deactivate-demo')->assertSuccessful();

        $this->artisan('catalog:deactivate-demo', ['--reactivate' => true])->assertSuccessful();

        $this->assertTrue($demo->fresh()->is_active);
        $this->assertTrue(Brand::whereKey($demo->brand_id)->sole()->is_active);
    }

    /**
     * « Gorée Soins » n'a aucun produit, mais figure dans la liste des marques
     * du site : la reconnaître par son nom est le seul moyen de la retirer.
     */
    public function test_it_hides_a_demo_brand_that_has_no_product(): void
    {
        $orphan = Brand::factory()->create(['name' => 'Gorée Soins', 'is_active' => true]);

        $this->artisan('catalog:deactivate-demo')->assertSuccessful();

        $this->assertFalse($orphan->fresh()->is_active);
    }

    /**
     * La liste des marques fictives vient du seeder : la dupliquer dans la
     * commande laisserait les deux diverger, et une marque ajoutée au seeder
     * resterait visible sur le site.
     */
    public function test_every_seeded_brand_is_covered(): void
    {
        foreach (BrandSeeder::BRANDS as $seeded) {
            Brand::factory()->create(['name' => $seeded['name'], 'is_active' => true]);
        }

        $this->artisan('catalog:deactivate-demo')->assertSuccessful();

        $this->assertSame(
            0,
            Brand::whereIn('name', array_column(BrandSeeder::BRANDS, 'name'))->where('is_active', true)->count(),
        );
    }

    public function test_it_reports_when_there_is_nothing_to_do(): void
    {
        $this->importedProduct();

        $this->artisan('catalog:deactivate-demo')
            ->expectsOutputToContain('Rien à faire')
            ->assertSuccessful();
    }
}
