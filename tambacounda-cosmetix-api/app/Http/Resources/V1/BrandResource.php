<?php

namespace App\Http\Resources\V1;

use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Brand */
class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'website' => $this->website,
            'logo_url' => $this->logo ? app(StorageService::class)->url($this->logo) : null,
            // Only on the list/detail endpoints that compute it (not when nested in a product).
            'products_count' => $this->whenHas('products_count', fn ($count) => (int) $count),
        ];
    }
}
