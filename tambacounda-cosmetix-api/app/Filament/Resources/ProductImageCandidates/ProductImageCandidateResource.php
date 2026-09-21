<?php

namespace App\Filament\Resources\ProductImageCandidates;

use App\Filament\Resources\ProductImageCandidates\Pages\ListProductImageCandidates;
use App\Filament\Resources\ProductImageCandidates\Tables\ProductImageCandidatesTable;
use App\Models\ProductImageCandidate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Écran de validation des photos trouvées automatiquement.
 *
 * C'est le point de passage obligé entre la commande products:source-images
 * et la boutique : tant qu'un humain n'a pas comparé ici le nom du produit et
 * la photo proposée, la fiche reste sans image. La pastille de navigation
 * compte les propositions en attente pour que la file ne s'oublie pas.
 */
class ProductImageCandidateResource extends Resource
{
    protected static ?string $model = ProductImageCandidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Photos à valider';

    protected static ?string $modelLabel = 'photo proposée';

    protected static ?string $pluralModelLabel = 'photos proposées';

    protected static ?int $navigationSort = 6;

    public static function table(Table $table): Table
    {
        return ProductImageCandidatesTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = ProductImageCandidate::query()->pending()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** Les propositions se créent par la commande, jamais à la main. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductImageCandidates::route('/'),
        ];
    }
}
