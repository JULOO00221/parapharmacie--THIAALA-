<?php

namespace App\Filament\Resources\ProductImageCandidates\Tables;

use App\Models\ProductImageCandidate;
use App\Services\ImageSourcing\ImageCandidateReviewer;
use App\Services\StorageService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * La table met en regard, sur une même ligne, le produit du catalogue et la
 * photo proposée : c'est cette comparaison qui constitue la validation. Tout
 * le reste — score, licence, requête utilisée — n'est là que pour expliquer
 * d'où vient la proposition.
 */
class ProductImageCandidatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('confidence', 'desc')
            ->columns([
                ImageColumn::make('path')
                    ->label('Photo trouvée')
                    ->disk(app(StorageService::class)->disk())
                    ->height(96)
                    ->width(96)
                    ->extraImgAttributes(['class' => 'object-contain bg-white rounded-lg'])
                    ->placeholder('Non téléchargée'),

                TextColumn::make('product.name')
                    ->label('Produit du catalogue')
                    ->weight('bold')
                    ->wrap()
                    ->searchable()
                    ->description(fn (ProductImageCandidate $record): string => trim(sprintf(
                        '%s · réf. %s',
                        $record->product->brand?->name ?? 'sans marque',
                        $record->product->sku,
                    ))),

                TextColumn::make('source_product_name')
                    ->label('Fiche Open Beauty Facts')
                    ->wrap()
                    ->searchable()
                    ->url(fn (ProductImageCandidate $record): ?string => $record->source_page_url)
                    ->openUrlInNewTab()
                    ->description(fn (ProductImageCandidate $record): string => trim(sprintf(
                        '%s%s',
                        $record->source_brand ?? 'marque inconnue',
                        $record->source_quantity !== null ? ' · '.$record->source_quantity : '',
                    ))),

                TextColumn::make('confidence')
                    ->label('Confiance')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => $state.'/100')
                    ->color(fn (int $state): string => match (true) {
                        $state >= 85 => 'success',
                        $state >= 65 => 'warning',
                        default => 'danger',
                    })
                    ->sortable()
                    ->description(fn (ProductImageCandidate $record): string => $record->match_method === ProductImageCandidate::METHOD_BARCODE
                        ? 'code-barres'
                        : 'marque + nom'),

                TextColumn::make('attribution')
                    ->label('Licence')
                    ->wrap()
                    ->color('gray')
                    ->description(fn (ProductImageCandidate $record): string => (string) $record->license_code)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('bytes')
                    ->label('Poids')
                    ->formatStateUsing(fn (?int $state): string => $state === null
                        ? '—'
                        : number_format($state / 1024, 1, ',', ' ').' Ko')
                    ->description(fn (ProductImageCandidate $record): string => $record->width !== null
                        ? $record->width.'×'.$record->height.' px'
                        : '')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        ProductImageCandidate::STATUS_APPROVED => 'Validée',
                        ProductImageCandidate::STATUS_REJECTED => 'Rejetée',
                        default => 'En attente',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        ProductImageCandidate::STATUS_APPROVED => 'success',
                        ProductImageCandidate::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        ProductImageCandidate::STATUS_PENDING => 'En attente',
                        ProductImageCandidate::STATUS_APPROVED => 'Validée',
                        ProductImageCandidate::STATUS_REJECTED => 'Rejetée',
                    ])
                    // La file de travail, c'est ce qui est en attente : c'est
                    // donc ce que l'écran montre en arrivant.
                    ->default(ProductImageCandidate::STATUS_PENDING),

                SelectFilter::make('product.brand_id')
                    ->label('Marque')
                    ->relationship('product.brand', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ProductImageCandidate $record): bool => $record->isPending())
                    ->requiresConfirmation()
                    ->modalHeading('Publier cette photo sur la fiche produit ?')
                    ->modalDescription(fn (ProductImageCandidate $record): string => sprintf(
                        'La photo sera publiée sur « %s », accompagnée de son attribution : %s.',
                        $record->product->name,
                        $record->attribution,
                    ))
                    ->action(function (ProductImageCandidate $record, ImageCandidateReviewer $reviewer): void {
                        try {
                            $reviewer->approve($record, Auth::user());
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->danger()
                                ->title('Photo non publiée')
                                ->body($exception->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Photo publiée')
                            ->body("« {$record->product->name} » a désormais une photo.")
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Rejeter')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ProductImageCandidate $record): bool => $record->isPending())
                    ->schema([
                        Textarea::make('note')
                            ->label('Motif (facultatif)')
                            ->placeholder('Ex. : mauvais format, produit différent, photo illisible.')
                            ->maxLength(500),
                    ])
                    ->action(function (ProductImageCandidate $record, array $data, ImageCandidateReviewer $reviewer): void {
                        $reviewer->reject($record, Auth::user(), $data['note'] ?? null);

                        Notification::make()
                            ->success()
                            ->title('Proposition rejetée')
                            ->body('Cette photo ne sera plus proposée pour ce produit.')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('rejectSelected')
                        ->label('Rejeter la sélection')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, ImageCandidateReviewer $reviewer): void {
                            $records->each(fn (ProductImageCandidate $record) => $reviewer->reject(
                                $record,
                                Auth::user(),
                                'Rejet groupé.',
                            ));

                            Notification::make()
                                ->success()
                                ->title($records->count().' proposition(s) rejetée(s)')
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('Aucune photo à valider')
            ->emptyStateDescription('Lancez « php artisan products:source-images » pour chercher des photos.');
    }
}
