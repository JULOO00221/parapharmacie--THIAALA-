<?php

namespace App\Filament\Resources\Stocks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class StocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Produit')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('store.name')
                    ->label('Boutique')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity_available')
                    ->label('Disponible')
                    ->sortable(),
                TextColumn::make('quantity_reserved')
                    ->label('Réservée')
                    ->sortable(),
                TextColumn::make('alert_threshold')
                    ->label('Seuil d\'alerte')
                    ->sortable(),
                IconColumn::make('low_stock')
                    ->label('Stock faible')
                    ->boolean()
                    ->getStateUsing(
                        fn ($record) => $record->alert_threshold !== null
                            && $record->quantity_available <= $record->alert_threshold
                    ),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label('Boutique')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('product_id')
                    ->label('Produit')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('low_stock')
                    ->label('Stock faible')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('alert_threshold')
                            ->whereColumn('quantity_available', '<=', 'alert_threshold'),
                        false: fn ($query) => $query->whereNull('alert_threshold')
                            ->orWhereColumn('quantity_available', '>', 'alert_threshold'),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
