<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tag;
use App\Services\ProductService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Curated demo catalog for a Senegalese parapharmacy/cosmetics store.
     * All names, brands and prices (FCFA) are fictional. `category` and
     * `brand` reference the slugs created by ProductCategorySeeder and
     * BrandSeeder. `stock` is read by StockSeeder so both seeders share
     * a single source of truth instead of maintaining two parallel lists.
     */
    public const PRODUCTS = [
        ['sku' => 'TC-0001', 'name' => 'Crème hydratante karité intense', 'category' => 'cremes-hydratantes', 'brand' => 'Baobab Soins', 'price' => 4500, 'cost_price' => 2500, 'compare_at_price' => 5000, 'tags' => ['Hydratant', 'Bio'], 'stock' => ['available' => 40, 'threshold' => 8]],
        ['sku' => 'TC-0002', 'name' => 'Crème anti-âge regenera', 'category' => 'cremes-hydratantes', 'brand' => 'Sénégal Éclat', 'price' => 8500, 'cost_price' => 5000, 'tags' => ['Anti-âge', 'Peau sensible'], 'stock' => ['available' => 15, 'threshold' => 5]],
        ['sku' => 'TC-0003', 'name' => 'Baume apaisant aloé douceur', 'category' => 'cremes-hydratantes', 'brand' => 'Casamance Bio', 'price' => 3800, 'cost_price' => 2000, 'tags' => ['Bio', 'Peau sensible', 'Sans parfum'], 'stock' => ['available' => 25, 'threshold' => 6]],
        ['sku' => 'TC-0004', 'name' => 'Gel nettoyant purifiant visage frais', 'category' => 'nettoyants-visage', 'brand' => 'Dakar Beauté', 'price' => 3200, 'cost_price' => 1700, 'tags' => ['Naturel'], 'stock' => ['available' => 30, 'threshold' => 6]],
        ['sku' => 'TC-0005', 'name' => 'Eau micellaire douceur teranga', 'category' => 'nettoyants-visage', 'brand' => 'Teranga Cosmétique', 'price' => 2900, 'cost_price' => 1500, 'tags' => ['Peau sensible', 'Sans parfum'], 'stock' => ['available' => 35, 'threshold' => 8]],
        ['sku' => 'TC-0006', 'name' => 'Mousse nettoyante néem pur', 'category' => 'nettoyants-visage', 'brand' => 'Sahel Pharma', 'price' => 3100, 'cost_price' => 1600, 'tags' => ['Naturel', 'Bio'], 'stock' => ['available' => 4, 'threshold' => 6]],
        ['sku' => 'TC-0007', 'name' => 'Savon noir traditionnel', 'category' => 'savons-gels-douche', 'brand' => 'Baobab Soins', 'price' => 1500, 'cost_price' => 700, 'tags' => ['Naturel', 'Made in Senegal', 'Best-seller'], 'stock' => ['available' => 60, 'threshold' => 12], 'is_featured' => true],
        ['sku' => 'TC-0008', 'name' => 'Gel douche coco vanille', 'category' => 'savons-gels-douche', 'brand' => 'Dakar Beauté', 'price' => 2200, 'cost_price' => 1100, 'tags' => ['Hydratant'], 'stock' => ['available' => 28, 'threshold' => 6]],
        ['sku' => 'TC-0009', 'name' => 'Savon au lait de karité', 'category' => 'savons-gels-douche', 'brand' => 'Ndar Naturel', 'price' => 1800, 'cost_price' => 900, 'tags' => ['Bio', 'Made in Senegal'], 'stock' => ['available' => 50, 'threshold' => 10]],
        ['sku' => 'TC-0010', 'name' => 'Huile de baobab pure', 'category' => 'huiles-corporelles', 'brand' => 'Baobab Soins', 'price' => 5200, 'cost_price' => 3000, 'tags' => ['Bio', 'Naturel', 'Made in Senegal'], 'stock' => ['available' => 20, 'threshold' => 5], 'is_featured' => true],
        ['sku' => 'TC-0011', 'name' => 'Huile de coco vierge', 'category' => 'huiles-corporelles', 'brand' => 'Casamance Bio', 'price' => 4800, 'cost_price' => 2700, 'compare_at_price' => 5300, 'tags' => ['Bio', 'Hydratant'], 'stock' => ['available' => 22, 'threshold' => 5]],
        ['sku' => 'TC-0012', 'name' => 'Huile de moringa fortifiante', 'category' => 'huiles-corporelles', 'brand' => 'Sahel Pharma', 'price' => 5000, 'cost_price' => 2900, 'tags' => ['Naturel'], 'stock' => ['available' => 18, 'threshold' => 5]],
        ['sku' => 'TC-0013', 'name' => 'Shampoing karité & miel', 'category' => 'shampoings', 'brand' => 'Baobab Soins', 'price' => 3400, 'cost_price' => 1800, 'tags' => ['Hydratant', 'Cheveux crépus'], 'stock' => ['available' => 32, 'threshold' => 8]],
        ['sku' => 'TC-0014', 'name' => 'Shampoing doux usage fréquent', 'category' => 'shampoings', 'brand' => 'Dakar Beauté', 'price' => 2600, 'cost_price' => 1300, 'tags' => ['Sans parfum'], 'stock' => ['available' => 26, 'threshold' => 6]],
        ['sku' => 'TC-0015', 'name' => 'Shampoing anti-pelliculaire', 'category' => 'shampoings', 'brand' => 'Thiès Pharma', 'price' => 3900, 'cost_price' => 2100, 'tags' => ['Hypoallergénique'], 'stock' => ['available' => 3, 'threshold' => 5]],
        ['sku' => 'TC-0016', 'name' => 'Masque capillaire réparateur', 'category' => 'soins-capillaires', 'brand' => 'Ndar Naturel', 'price' => 4200, 'cost_price' => 2300, 'tags' => ['Cheveux crépus', 'Hydratant'], 'stock' => ['available' => 19, 'threshold' => 5]],
        ['sku' => 'TC-0017', 'name' => 'Sérum pousse cheveux ricin', 'category' => 'soins-capillaires', 'brand' => 'Kaolack Vital', 'price' => 3700, 'cost_price' => 2000, 'tags' => ['Naturel', 'Cheveux crépus'], 'stock' => ['available' => 24, 'threshold' => 6]],
        ['sku' => 'TC-0018', 'name' => 'Lait de toilette bébé', 'category' => 'hygiene-bebe', 'brand' => 'Teranga Cosmétique', 'price' => 2400, 'cost_price' => 1200, 'tags' => ['Peau sensible', 'Sans parfum', 'Hypoallergénique'], 'stock' => ['available' => 33, 'threshold' => 8]],
        ['sku' => 'TC-0019', 'name' => 'Savon surgras bébé', 'category' => 'hygiene-bebe', 'brand' => 'Sénégal Éclat', 'price' => 1600, 'cost_price' => 800, 'tags' => ['Peau sensible', 'Hypoallergénique'], 'stock' => ['available' => 45, 'threshold' => 10]],
        ['sku' => 'TC-0020', 'name' => 'Gel hygiène intime doux', 'category' => 'hygiene-intime', 'brand' => 'Sahel Pharma', 'price' => 2800, 'cost_price' => 1400, 'tags' => ['Peau sensible', 'Hypoallergénique'], 'stock' => ['available' => 21, 'threshold' => 6]],
        ['sku' => 'TC-0021', 'name' => 'Complément vitamine C baobab', 'category' => 'complements-alimentaires', 'brand' => 'Kaolack Vital', 'price' => 6500, 'cost_price' => 3800, 'tags' => ['Naturel', 'Nouveauté'], 'weight' => null, 'stock' => ['available' => 16, 'threshold' => 5]],
        ['sku' => 'TC-0022', 'name' => 'Gélules fer & vitalité', 'category' => 'complements-alimentaires', 'brand' => 'Thiès Pharma', 'price' => 5800, 'cost_price' => 3200, 'tags' => ['Nouveauté'], 'weight' => null, 'requires_prescription' => true, 'stock' => ['available' => 12, 'threshold' => 5]],
        ['sku' => 'TC-0023', 'name' => 'Crème solaire SPF50 teranga', 'category' => 'solaires', 'brand' => 'Teranga Cosmétique', 'price' => 7200, 'cost_price' => 4000, 'tags' => ['SPF', 'Peau sensible'], 'stock' => ['available' => 27, 'threshold' => 6], 'is_featured' => true],
        ['sku' => 'TC-0024', 'name' => 'Stick solaire lèvres SPF30', 'category' => 'solaires', 'brand' => 'Dakar Beauté', 'price' => 2500, 'cost_price' => 1300, 'tags' => ['SPF', 'Best-seller'], 'stock' => ['available' => 38, 'threshold' => 8]],
    ];

    public function run(): void
    {
        $service = app(ProductService::class);

        foreach (self::PRODUCTS as $definition) {
            $category = ProductCategory::where('slug', $definition['category'])->firstOrFail();
            $brand = Brand::where('slug', Str::slug($definition['brand']))->firstOrFail();

            $product = Product::updateOrCreate(
                ['sku' => $definition['sku']],
                [
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'name' => $definition['name'],
                    'slug' => Str::slug($definition['name']),
                    'barcode' => null,
                    'short_description' => $definition['name'],
                    'description' => sprintf(
                        '%s — produit de démonstration pour la parapharmacie Tambacounda Cosmetix.',
                        $definition['name']
                    ),
                    'price' => $definition['price'],
                    'cost_price' => $definition['cost_price'],
                    'compare_at_price' => $definition['compare_at_price'] ?? null,
                    'tax_rate' => 18.00,
                    'is_active' => true,
                    'is_featured' => $definition['is_featured'] ?? false,
                    'requires_prescription' => $definition['requires_prescription'] ?? false,
                    'weight' => array_key_exists('weight', $definition) ? $definition['weight'] : 0.15,
                    'sort_order' => 0,
                ]
            );

            $tagIds = Tag::whereIn(
                'slug',
                array_map(fn (string $tag) => Str::slug($tag), $definition['tags'])
            )->pluck('id')->all();

            $service->syncTags($product, $tagIds);
        }
    }
}
