<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public payload for the checkout store picker — only what's needed to
 * pick a store_id and display it. is_active is never exposed: filtering
 * already happens server-side (StoreController only lists active stores).
 *
 * @mixin \App\Models\Store
 */
class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}
