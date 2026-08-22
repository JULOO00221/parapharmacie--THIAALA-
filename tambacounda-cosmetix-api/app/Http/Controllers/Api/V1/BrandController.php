<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BrandResource;
use App\Models\Brand;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $brands = Brand::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return BrandResource::collection($brands);
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
