<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'path',
    'alt_text',
    'sort_order',
    'is_primary',
    'source',
    'source_url',
    'license_code',
    'license_url',
    'attribution',
])]
class ProductImage extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Une photo sous licence externe ne peut être affichée qu'accompagnée de
     * son attribution. Les photos maison n'en ont pas.
     */
    public function requiresAttribution(): bool
    {
        return $this->attribution !== null;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
