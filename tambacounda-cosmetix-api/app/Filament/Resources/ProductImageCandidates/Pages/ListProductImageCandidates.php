<?php

namespace App\Filament\Resources\ProductImageCandidates\Pages;

use App\Filament\Resources\ProductImageCandidates\ProductImageCandidateResource;
use App\Models\ProductImageCandidate;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListProductImageCandidates extends ListRecords
{
    protected static string $resource = ProductImageCandidateResource::class;

    public function getSubheading(): string
    {
        return 'Chaque photo vient d\'Open Beauty Facts et n\'est publiée qu\'après votre validation. '
            .'Vérifiez que la photo correspond bien au produit : le score est une aide, pas une preuve.';
    }

    protected function getTableQuery(): Builder
    {
        // Le produit et sa marque sont affichés sur chaque ligne : les charger
        // d'avance évite une requête par ligne.
        return ProductImageCandidate::query()->with(['product.brand']);
    }
}
