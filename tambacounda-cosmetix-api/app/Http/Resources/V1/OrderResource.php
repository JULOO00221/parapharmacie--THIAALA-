<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public order payload. Deliberately never includes the internal
 * auto-incrementing id (order_number is the only identifier exposed —
 * see the Phase 6 order_number design), user_id, idempotency_key, or any
 * stock figure. `store` only exposes id/name — same minimal shape as
 * StoreResource, never is_active or other internal state.
 *
 * @mixin \App\Models\Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'customer' => [
                'name' => $this->customer_name,
                'phone' => $this->customer_phone,
                'email' => $this->customer_email,
            ],
            'store' => $this->whenLoaded('store', fn () => [
                'id' => $this->store->id,
                'name' => $this->store->name,
            ]),
            'delivery' => [
                'is_pickup' => $this->is_pickup,
                'zone' => $this->whenLoaded('deliveryZone', fn () => $this->deliveryZone !== null ? [
                    'id' => $this->deliveryZone->id,
                    'name' => $this->deliveryZone->name,
                    'fee' => $this->deliveryZone->fee,
                ] : null),
                'address' => $this->delivery_address,
            ],
            'notes' => $this->notes,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'subtotal' => $this->subtotal,
            'delivery_fee' => $this->delivery_fee,
            'total' => $this->total,
            'currency' => 'XOF',
            'created_at' => $this->created_at,
        ];
    }
}
