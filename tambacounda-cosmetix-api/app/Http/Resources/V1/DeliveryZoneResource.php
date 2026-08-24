<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public payload for the checkout delivery zone picker. is_active is
 * never exposed — filtering already happens server-side
 * (DeliveryZoneController only lists active zones).
 *
 * @mixin \App\Models\DeliveryZone
 */
class DeliveryZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'fee' => $this->fee,
        ];
    }
}
