<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProductIndexRequest;
use App\Http\Resources\V1\ProductResource;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    private const EAGER_LOAD = ['brand', 'category', 'tags', 'images', 'primaryImage', 'stocks'];

    public function index(ProductIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = Product::query()
            ->with(self::EAGER_LOAD)
            ->where('is_active', true);

        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $query) use ($term) {
                $query->where('name', 'ILIKE', $term)
                    ->orWhere('short_description', 'ILIKE', $term)
                    ->orWhere('description', 'ILIKE', $term);
            });
        }

        if (! empty($filters['category'])) {
            // Includes subcategories at any depth: a parent category (e.g.
            // "Soins du visage") holds no product directly.
            $query->inCategoryTree($filters['category']);
        }

        if (! empty($filters['brand'])) {
            $query->whereHas('brand', fn (Builder $query) => $query->where('slug', $filters['brand']));
        }

        if (! empty($filters['tags'])) {
            $tagSlugs = array_values(array_filter(array_map('trim', explode(',', $filters['tags']))));

            if ($tagSlugs !== []) {
                $query->whereHas('tags', fn (Builder $query) => $query->whereIn('slug', $tagSlugs));
            }
        }

        if (isset($filters['price_min'])) {
            $query->where('price', '>=', $filters['price_min']);
        }

        if (isset($filters['price_max'])) {
            $query->where('price', '<=', $filters['price_max']);
        }

        if (array_key_exists('featured', $filters)) {
            $query->where('is_featured', filter_var($filters['featured'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('in_stock', $filters)) {
            $wantsInStock = filter_var($filters['in_stock'], FILTER_VALIDATE_BOOLEAN);
            $hasSellableStock = fn (Builder $query) => $query->whereColumn('quantity_available', '>', 'quantity_reserved');

            $wantsInStock
                ? $query->whereHas('stocks', $hasSellableStock)
                : $query->whereDoesntHave('stocks', $hasSellableStock);
        }

        match ($filters['sort'] ?? 'newest') {
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'featured_first' => $query->orderByDesc('is_featured')->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        $products = $query->paginate($filters['per_page'] ?? 24)->withQueryString();

        return ProductResource::collection($products);
    }

    public function show(string $slug): ProductResource
    {
        $product = Product::query()
            ->with(self::EAGER_LOAD)
            ->where('is_active', true)
            ->where('slug', $slug)
            ->firstOrFail();

        return ProductResource::make($product);
    }
}
