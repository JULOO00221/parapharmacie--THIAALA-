<?php

namespace App\Filament\Resources\DeliveryZones\Schemas;

use App\Models\DeliveryZone;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DeliveryZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255)
                    ->unique(DeliveryZone::class, 'name', ignoreRecord: true),
                TextInput::make('fee')
                    ->label('Frais de livraison (FCFA)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
