<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Store;
use App\Services\ProductService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StocksRelationManager extends RelationManager
{
    protected static string $relationship = 'stocks';

    protected static ?string $title = 'Stock par boutique';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('store_id')
                    ->label('Boutique')
                    ->relationship('store', 'name')
                    ->required()
                    ->unique(
                        table: 'stocks',
                        column: 'store_id',
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule, RelationManager $livewire) => $rule->where('product_id', $livewire->getOwnerRecord()->id),
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('store.name')
            ->columns([
                TextColumn::make('store.name')
                    ->label('Boutique')
                    ->searchable(),
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
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data, RelationManager $livewire): Model {
                        return app(ProductService::class)->setInitialStock(
                            $livewire->getOwnerRecord(),
                            Store::findOrFail($data['store_id']),
                            (int) $data['quantity_available'],
                            (int) ($data['quantity_reserved'] ?? 0),
                            isset($data['alert_threshold']) ? (int) $data['alert_threshold'] : null,
                        );
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
