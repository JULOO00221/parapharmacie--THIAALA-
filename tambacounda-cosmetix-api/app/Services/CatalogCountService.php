<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Product counts for the catalogue filters. Every count matches exactly
 * what GET /products returns for the same filter: active products only
 * (soft-deleted ones excluded), and a category counts the products of its
 * whole subtree — the same rule as Product::inCategoryTree().
 *
 * Query cost is constant, whatever the number of categories, brands or
 * products: no query per category or per brand.
 */
class CatalogCountService
{
    /**
     * Sets `products_count` on each category — and on its loaded children —
     * to the number of active products in its subtree, optionally limited to
     * one brand.
     *
     * Two queries: product counts grouped by category, and the parent link of
     * every category (inactive ones included, since the product filter's
     * recursion does not skip them either). Subtree totals are then summed
     * in memory by walking each category's ancestors.
     *
     * @param  Collection<int, ProductCategory>  $categories
     */
    public function attachCategoryCounts(Collection $categories, ?string $brandSlug = null): void
    {
        $totals = $this->categoryTotals($brandSlug);

        foreach ($categories as $category) {
            $category->setAttribute('products_count', $totals[$category->id] ?? 0);

            if ($category->relationLoaded('children')) {
                foreach ($category->children as $child) {
                    $child->setAttribute('products_count', $totals[$child->id] ?? 0);
                }
            }
        }
    }

    /**
     * Active brands with `products_count`. With a category, only the brands
     * that have active products in that category's subtree, counted within
     * it. One query (the counts are correlated subqueries of that query).
     *
     * @return Collection<int, Brand>
     */
    public function brandsWithCounts(?string $categorySlug = null): Collection
    {
        $inScope = function (Builder $products) use ($categorySlug): void {
            $products->where('is_active', true);

            if ($categorySlug !== null) {
                $products->inCategoryTree($categorySlug);
            }
        };

        return Brand::query()
            ->where('is_active', true)
            ->when($categorySlug !== null, fn (Builder $query) => $query->whereHas('products', $inScope))
            ->withCount(['products' => $inScope])
            ->orderBy('name')
            ->get();
    }

    /**
     * Subtree totals for every category id.
     *
     * @return array<int, int>
     */
    private function categoryTotals(?string $brandSlug): array
    {
        /** @var array<int, int> $direct */
        $direct = Product::query()
            ->where('is_active', true)
            ->when($brandSlug !== null, fn (Builder $query) => $query->whereHas(
                'brand',
                fn (Builder $brand) => $brand->where('slug', $brandSlug),
            ))
            ->selectRaw('category_id, COUNT(*) AS aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id')
            ->map(fn ($count) => (int) $count)
            ->all();

        /** @var array<int, int|null> $parentOf */
        $parentOf = ProductCategory::query()->pluck('parent_id', 'id')->all();

        $totals = [];

        foreach ($direct as $categoryId => $count) {
            // Walk up to the root; $visited stops a (corrupt) parent_id cycle.
            $visited = [];
            for ($id = $categoryId; $id !== null && ! isset($visited[$id]); $id = $parentOf[$id] ?? null) {
                $visited[$id] = true;
                $totals[$id] = ($totals[$id] ?? 0) + $count;
            }
        }

        return $totals;
    }
}
