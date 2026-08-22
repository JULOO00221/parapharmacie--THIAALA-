<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'store_id',
    'quantity_available',
    'quantity_reserved',
    'alert_threshold',
])]
class Stock extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity_available' => 'integer',
            'quantity_reserved' => 'integer',
            'alert_threshold' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
