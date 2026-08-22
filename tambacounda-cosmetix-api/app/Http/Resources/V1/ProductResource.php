<?php

namespace App\Http\Resources\V1;

use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public product payload. Deliberately never includes cost_price, margin,
 * barcode, raw stock quantities/thresholds, or any other internal/admin
 * field — see availability() below for how stock is represented instead.
 *
 * @mixin \App\Models\Product
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $availability = app(ProductService::class)->availability($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'tax_rate' => $this->tax_rate,
            'weight' => $this->weight,
            'requires_prescription' => $this->requires_prescription,
            'featured' => $this->is_featured,
            'brand' => BrandResource::make($this->whenLoaded('brand')),
            'category' => ProductCategoryResource::make($this->whenLoaded('category')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'primary_image' => ProductImageResource::make($this->whenLoaded('primaryImage')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'available' => $availability['available'],
            'stock_status' => $availability['stock_status'],
        ];
    }
}
