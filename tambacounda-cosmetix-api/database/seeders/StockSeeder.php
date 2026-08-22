<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Store;
use App\Services\ProductService;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    /**
     * Requires StoreSeeder and ProductSeeder to have run first. Reads the
     * per-product stock hints from ProductSeeder::PRODUCTS so quantities
     * aren't maintained twice, and goes through ProductService so the
     * unique(product_id, store_id) upsert stays the single source of truth
     * for "create or update initial stock".
     */
    public function run(): void
    {
        $store = Store::where('slug', 'tambacounda-cosmetix')->firstOrFail();
        $service = app(ProductService::class);

        foreach (ProductSeeder::PRODUCTS as $definition) {
            $product = Product::where('sku', $definition['sku'])->first();

            if (! $product) {
                continue;
            }

            $service->setInitialStock(
                $product,
                $store,
                $definition['stock']['available'],
                $definition['stock']['reserved'] ?? 0,
                $definition['stock']['threshold'] ?? null,
            );
        }
    }
}
