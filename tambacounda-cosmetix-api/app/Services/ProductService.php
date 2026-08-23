<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Stock;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductService
{
    /**
     * Create a product. Wrapped in a transaction so a failure never leaves
     * a partially-written row, even though a single insert is one query today —
     * this keeps the behaviour stable if create() grows more writes later.
     */
    public function create(array $data): Product
    {
        $this->assertPriceConsistency(
            $data['price'] ?? null,
            $data['compare_at_price'] ?? null
        );

        return DB::transaction(fn () => Product::create($data));
    }

    public function update(Product $product, array $data): Product
    {
        $price = array_key_exists('price', $data) ? $data['price'] : $product->price;
        $compareAtPrice = array_key_exists('compare_at_price', $data)
            ? $data['compare_at_price']
            : $product->compare_at_price;

        $this->assertPriceConsistency($price, $compareAtPrice);

        return DB::transaction(function () use ($product, $data) {
            $product->update($data);

            return $product->refresh();
        });
    }

    public function activate(Product $product): Product
    {
        $product->update(['is_active' => true]);

        return $product;
    }

    public function deactivate(Product $product): Product
    {
        $product->update(['is_active' => false]);

        return $product;
    }

    public function toggleActive(Product $product): Product
    {
        $product->update(['is_active' => ! $product->is_active]);

        return $product;
    }

    /**
     * Returns null when cost_price is not known — margin cannot be computed.
     * Exposes both the markup rate (on price) and margin rate (on cost),
     * since French retail uses "taux de marque" and "taux de marge" for
     * these two distinct ratios.
     */
    public function calculateMargin(Product $product): ?array
    {
        if ($product->cost_price === null) {
            return null;
        }

        $price = (float) $product->price;
        $cost = (float) $product->cost_price;
        $amount = round($price - $cost, 2);

        return [
            'amount' => $amount,
            'markup_rate' => $price > 0 ? round(($amount / $price) * 100, 2) : null,
            'margin_rate' => $cost > 0 ? round(($amount / $cost) * 100, 2) : null,
        ];
    }

    public function addImage(
        Product $product,
        string $path,
        ?string $altText = null,
        bool $isPrimary = false,
        ?int $sortOrder = null
    ): ProductImage {
        return DB::transaction(function () use ($product, $path, $altText, $isPrimary, $sortOrder) {
            if ($isPrimary) {
                $product->images()->where('is_primary', true)->update(['is_primary' => false]);
            }

            return $product->images()->create([
                'path' => $path,
                'alt_text' => $altText,
                'sort_order' => $sortOrder ?? ((int) $product->images()->max('sort_order') + 1),
                'is_primary' => $isPrimary,
            ]);
        });
    }

    public function setPrimaryImage(ProductImage $image): ProductImage
    {
        return DB::transaction(function () use ($image) {
            ProductImage::where('product_id', $image->product_id)
                ->where('id', '!=', $image->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            $image->update(['is_primary' => true]);

            return $image;
        });
    }

    /**
     * Deletes the physical file first, then the database row. Storage's
     * delete() returns false rather than throwing when the file is already
     * absent, so a missing file never blocks removing the record.
     */
    public function removeImage(ProductImage $image): void
    {
        app(StorageService::class)->delete($image->path);

        $image->delete();
    }

    /**
     * Updates an image's attributes. When $data includes a new `path` (a
     * genuine replacement upload, already stored by the caller), the old
     * file is deleted ONLY after the database update succeeds — if the
     * update throws, the old file is left untouched and still referenced.
     */
    public function updateImage(ProductImage $image, array $data): ProductImage
    {
        return DB::transaction(function () use ($image, $data) {
            $previousPath = $image->path;

            if ($data['is_primary'] ?? false) {
                $this->setPrimaryImage($image);
                unset($data['is_primary']);
            }

            $image->update($data);

            $newPath = $data['path'] ?? null;

            if ($newPath !== null && $newPath !== $previousPath) {
                app(StorageService::class)->delete($previousPath);
            }

            return $image->refresh();
        });
    }

    /**
     * @param  array<int>  $tagIds
     */
    public function syncTags(Product $product, array $tagIds): array
    {
        return $product->tags()->sync($tagIds);
    }

    /**
     * Creates or updates the stock row for a product in a given store.
     * Idempotent: relies on the unique(product_id, store_id) constraint.
     * Stock movements, lots and expiration are out of scope here (Phase 5).
     */
    public function setInitialStock(
        Product $product,
        Store $store,
        int $quantityAvailable,
        int $quantityReserved = 0,
        ?int $alertThreshold = null
    ): Stock {
        return Stock::updateOrCreate(
            ['product_id' => $product->id, 'store_id' => $store->id],
            [
                'quantity_available' => $quantityAvailable,
                'quantity_reserved' => $quantityReserved,
                'alert_threshold' => $alertThreshold,
            ]
        );
    }

    /**
     * Public availability for the storefront: never exposes raw quantities,
     * only a boolean and a coarse status derived from sellable stock
     * (quantity_available - quantity_reserved) summed across every store
     * the product has a stock row in — there's no per-store storefront yet,
     * so this aggregates rather than assuming a single "default" store.
     *
     * @return array{available: bool, stock_status: string}
     */
    public function availability(Product $product): array
    {
        $stocks = $product->relationLoaded('stocks') ? $product->stocks : $product->stocks()->get();

        $sellable = $stocks->sum(
            fn (Stock $stock) => max(0, $stock->quantity_available - $stock->quantity_reserved)
        );

        $thresholds = $stocks->pluck('alert_threshold')->filter(fn (?int $threshold) => $threshold !== null);
        $threshold = $thresholds->isNotEmpty() ? $thresholds->min() : null;

        $status = match (true) {
            $sellable <= 0 => 'out_of_stock',
            $threshold !== null && $sellable <= $threshold => 'low_stock',
            default => 'in_stock',
        };

        return [
            'available' => $product->is_active && $sellable > 0,
            'stock_status' => $status,
        ];
    }

    private function assertPriceConsistency(mixed $price, mixed $compareAtPrice): void
    {
        if ($price === null || $compareAtPrice === null) {
            return;
        }

        if ((float) $compareAtPrice < (float) $price) {
            throw new InvalidArgumentException(
                'compare_at_price must be greater than or equal to price.'
            );
        }
    }
}
