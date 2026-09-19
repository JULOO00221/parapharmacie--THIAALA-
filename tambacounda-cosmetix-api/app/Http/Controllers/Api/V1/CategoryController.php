<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Services\CatalogCountService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * Each category (and each embedded child) carries `products_count`: the
     * active products of its whole subtree, as GET /products?category= would
     * return them. `?brand=` limits the counts to one brand.
     */
    public function index(Request $request, CatalogCountService $counts): AnonymousResourceCollection
    {
        $filters = $request->validate(['brand' => ['nullable', 'string', 'max:255']]);

        $categories = ProductCategory::query()
            ->with(['parent', 'children'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $counts->attachCategoryCounts($categories, $filters['brand'] ?? null);

        return ProductCategoryResource::collection($categories);
    }

    public function show(string $slug, CatalogCountService $counts): ProductCategoryResource
    {
        $category = ProductCategory::query()
            ->with(['parent', 'children'])
            ->where('is_active', true)
            ->where('slug', $slug)
            ->firstOrFail();

        $counts->attachCategoryCounts($category->newCollection([$category]));

        return ProductCategoryResource::make($category);
    }
}
