<?php

namespace App\Filament\Resources\Stocks\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label('Produit')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Select::make('store_id')
                    ->label('Boutique')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(
                        table: 'stocks',
                        column: 'store_id',
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule, Get $get) => $rule->where('product_id', $get('product_id')),
                    ),
                TextInput::make('quantity_available')
                    ->label('Quantité disponible')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                TextInput::make('quantity_reserved')
                    ->label('Quantité réservée')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                TextInput::make('alert_threshold')
                    ->label('Seuil d\'alerte')
                    ->numeric()
                    ->minValue(0),
            ]);
    }
}
