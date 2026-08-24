<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_number',
    'user_id',
    'store_id',
    'delivery_zone_id',
    'customer_name',
    'customer_phone',
    'customer_email',
    'is_pickup',
    'delivery_address',
    'notes',
    'subtotal',
    'delivery_fee',
    'total',
    'status',
    'payment_method',
    'payment_status',
    'idempotency_key',
])]
class Order extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_pickup' => 'boolean',
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
