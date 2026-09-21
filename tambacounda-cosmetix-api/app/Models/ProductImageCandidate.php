<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Photo trouvée sur une source externe, en attente d'une décision humaine.
 *
 * Une ligne de cette table n'est jamais visible côté boutique : seule la
 * validation dans le back-office crée le ProductImage correspondant. C'est
 * la garantie qu'aucune photo automatique n'atterrit sur une fiche produit
 * sans qu'un humain ait comparé le nom du produit et l'image.
 */
#[Fillable([
    'product_id',
    'source',
    'source_code',
    'source_image_url',
    'source_page_url',
    'source_product_name',
    'source_brand',
    'source_quantity',
    'license_code',
    'license_url',
    'attribution',
    'match_method',
    'search_query',
    'confidence',
    'confidence_breakdown',
    'path',
    'width',
    'height',
    'bytes',
    'status',
])]
class ProductImageCandidate extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const METHOD_BARCODE = 'barcode';

    public const METHOD_BRAND_NAME = 'brand_name';

    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'confidence_breakdown' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'bytes' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function productImage(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class);
    }

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** Une proposition issue d'un --dry-run n'a pas de fichier téléchargé. */
    public function hasDownloadedFile(): bool
    {
        return $this->path !== null;
    }
}
