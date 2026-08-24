<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DeliveryZoneResource;
use App\Models\DeliveryZone;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryZoneController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $zones = DeliveryZone::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return DeliveryZoneResource::collection($zones);
    }
}
