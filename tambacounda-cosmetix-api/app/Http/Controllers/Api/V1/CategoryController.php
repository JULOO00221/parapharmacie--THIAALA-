<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProductCategoryResource;
use App\Models\ProductCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = ProductCategory::query()
            ->with(['parent', 'children'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return ProductCategoryResource::collection($categories);
    }

    public function show(string $slug): ProductCategoryResource
    {
        $category = ProductCategory::query()
            ->with(['parent', 'children'])
            ->where('is_active', true)
            ->where('slug', $slug)
            ->firstOrFail();

        return ProductCategoryResource::make($category);
    }
}
