<?php

namespace App\Http\Resources\V1;

use App\Models\ProductImage;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductImage */
class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'url' => app(StorageService::class)->url($this->path),
            'alt_text' => $this->alt_text,
            'sort_order' => $this->sort_order,
            // Les photos venues d'une source externe sont sous licence
            // CC-BY-SA : leur attribution doit voyager avec l'image jusqu'à
            // l'affichage, faute de quoi la fiche produit ne respecte pas la
            // licence. Null pour les photos prises en boutique.
            'attribution' => $this->when(
                $this->attribution !== null,
                fn (): array => [
                    'text' => $this->attribution,
                    'license_code' => $this->license_code,
                    'license_url' => $this->license_url,
                    'source_url' => $this->source_url,
                ],
            ),
        ];
    }
}
