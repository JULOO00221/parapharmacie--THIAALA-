<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BrandResource;
use App\Models\Brand;
use App\Services\CatalogCountService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    /**
     * Each brand carries `products_count` (active products). With
     * `?category=`, only the brands that have products in that category's
     * subtree are returned, counted within it — the brands worth offering
     * as a filter on that category's page.
     */
    public function index(Request $request, CatalogCountService $counts): AnonymousResourceCollection
    {
        $filters = $request->validate(['category' => ['nullable', 'string', 'max:255']]);

        return BrandResource::collection($counts->brandsWithCounts($filters['category'] ?? null));
    }

    public function show(string $slug): BrandResource
    {
        $brand = Brand::query()
            ->where('is_active', true)
            ->where('slug', $slug)
            ->firstOrFail();

        return BrandResource::make($brand);
    }
}
