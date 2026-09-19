<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id',
    'brand_id',
    'name',
    'slug',
    'sku',
    'barcode',
    'short_description',
    'description',
    'price',
    'cost_price',
    'compare_at_price',
    'tax_rate',
    'is_active',
    'is_featured',
    'requires_prescription',
    'weight',
    'sort_order',
])]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'weight' => 'decimal:3',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'requires_prescription' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * Products of the category identified by $slug or of any of its
     * descendants, at any depth.
     *
     * The whole tree is resolved inside the product query by a recursive
     * CTE, so filtering costs no extra query whatever the number of
     * subcategories or levels. UNION (not UNION ALL) deduplicates visited
     * ids, which also stops the recursion if parent_id ever forms a cycle.
     */
    #[Scope]
    protected function inCategoryTree(Builder $query, string $slug): void
    {
        $query->whereRaw(
            'category_id IN (
                WITH RECURSIVE category_tree AS (
                    SELECT id FROM product_categories WHERE slug = ?
                    UNION
                    SELECT child.id FROM product_categories child
                    INNER JOIN category_tree parent ON child.parent_id = parent.id
                )
                SELECT id FROM category_tree
            )',
            [$slug],
        );
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tag');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
