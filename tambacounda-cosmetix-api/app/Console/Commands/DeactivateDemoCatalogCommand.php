<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Product;
use App\Services\ProductService;
use Database\Seeders\BrandSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Retire de la boutique le catalogue de démonstration semé en phase 2.
 *
 * Ces produits et ces marques sont fictifs — « Baobab Soins »,
 * « Crème hydratante karité intense » — et n'ont jamais été en vente. Ils ont
 * servi à construire le site avant l'import du vrai catalogue, et n'ont plus
 * de raison d'apparaître à côté des produits réellement vendus.
 *
 * Ils sont **désactivés, jamais supprimés** : is_active = false les retire de
 * l'API publique (et donc du site, de l'accueil et des compteurs de
 * catégories) tout en gardant intacts leurs stocks et les lignes de commande
 * qui les référencent — plusieurs produits de démonstration en ont. Une
 * suppression casserait cet historique ; --reactivate rend l'opération
 * réversible en une commande.
 */
class DeactivateDemoCatalogCommand extends Command
{
    /**
     * Les produits semés portent tous ce préfixe de référence, les produits
     * importés portant « THIAALA- ». C'est le seul critère parfaitement net :
     * le nom ou la marque demanderaient de juger au cas par cas.
     */
    private const DEMO_SKU_PREFIX = 'TC-';

    protected $signature = 'catalog:deactivate-demo
        {--dry-run : Affiche ce qui serait modifié sans rien écrire}
        {--reactivate : Fait l\'inverse et réaffiche les produits de démonstration}';

    protected $description = 'Retire du site les produits et marques de démonstration (sans rien supprimer).';

    public function handle(ProductService $products): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $deactivating = ! $this->option('reactivate');

        $demoProducts = $this->demoProducts($deactivating);
        $demoBrands = $this->demoBrands($deactivating);

        $this->intro($deactivating, $dryRun);

        if ($demoProducts->isEmpty() && $demoBrands->isEmpty()) {
            $this->info($deactivating
                ? 'Rien à faire : le catalogue de démonstration est déjà retiré du site.'
                : 'Rien à faire : le catalogue de démonstration est déjà visible.');

            return self::SUCCESS;
        }

        $this->renderPlan($demoProducts, $demoBrands);

        if ($dryRun) {
            $this->newLine();
            $this->warn('Simulation (--dry-run) : rien n\'a été modifié.');

            return self::SUCCESS;
        }

        // Chaque modèle est enregistré un par un, et non par un update groupé :
        // c'est ce qui déclenche CatalogCacheObserver, donc le vidage du cache
        // du frontend. Un UPDATE en masse contournerait Eloquent et laisserait
        // le site servir les produits retirés jusqu'à expiration du cache.
        DB::transaction(function () use ($demoProducts, $demoBrands, $deactivating, $products): void {
            foreach ($demoProducts as $product) {
                $deactivating ? $products->deactivate($product) : $products->activate($product);
            }

            foreach ($demoBrands as $brand) {
                $brand->update(['is_active' => ! $deactivating]);
            }
        });

        $this->newLine();
        $this->info(sprintf(
            '%d produit(s) et %d marque(s) %s. Le cache du catalogue du frontend a été programmé pour être vidé.',
            $demoProducts->count(),
            $demoBrands->count(),
            $deactivating ? 'retiré(e)s du site' : 'remis(es) en ligne',
        ));

        if ($deactivating) {
            $this->line('Aucune donnée n\'a été supprimée : « php artisan catalog:deactivate-demo --reactivate » annule l\'opération.');
        }

        return self::SUCCESS;
    }

    /**
     * Les produits de démonstration dont l'état doit encore changer. Ne
     * retenir que ceux-là rend la commande rejouable sans effet : la relancer
     * n'enregistre rien et ne redéclenche pas de vidage de cache.
     *
     * @return Collection<int, Product>
     */
    private function demoProducts(bool $deactivating): Collection
    {
        return Product::query()
            ->with('brand')
            ->where('sku', 'like', self::DEMO_SKU_PREFIX.'%')
            ->where('is_active', $deactivating)
            ->orderBy('sku')
            ->get();
    }

    /** @return Collection<int, Brand> */
    private function demoBrands(bool $deactivating): Collection
    {
        return Brand::query()
            ->whereIn('name', $this->demoBrandNames())
            ->where('is_active', $deactivating)
            ->orderBy('name')
            ->get();
    }

    /**
     * La liste vient du seeder lui-même, pour qu'une marque ajoutée là-bas ne
     * soit pas oubliée ici. Elle comprend « Gorée Soins », qui n'a aucun
     * produit mais apparaît quand même dans la liste des marques du site.
     *
     * @return list<string>
     */
    private function demoBrandNames(): array
    {
        return array_column(BrandSeeder::BRANDS, 'name');
    }

    private function intro(bool $deactivating, bool $dryRun): void
    {
        $this->newLine();
        $this->line($deactivating
            ? 'Retrait du catalogue de démonstration (produits <info>'.self::DEMO_SKU_PREFIX.'*</info> et marques fictives).'
            : 'Remise en ligne du catalogue de démonstration.');
        $this->line('Aucune suppression : seul le drapeau <info>is_active</info> change.');

        if ($dryRun) {
            $this->warn('Simulation (--dry-run) : aucune écriture.');
        }

        $this->newLine();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, Brand>  $brands
     */
    private function renderPlan(Collection $products, Collection $brands): void
    {
        if ($products->isNotEmpty()) {
            $this->line('<comment>Produits concernés</comment>');
            $this->table(
                ['Référence', 'Marque', 'Produit', 'Vedette'],
                $products->map(fn (Product $product): array => [
                    $product->sku,
                    $product->brand?->name ?? '—',
                    $product->name,
                    $product->is_featured ? 'oui' : '',
                ])->all(),
            );
        }

        if ($brands->isNotEmpty()) {
            $this->line('<comment>Marques concernées</comment>');
            $this->table(
                ['Marque', 'Produits rattachés'],
                $brands->map(fn (Brand $brand): array => [
                    $brand->name,
                    $brand->products()->count(),
                ])->all(),
            );
        }
    }
}
