<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Services\ProductService;
use App\Services\StorageService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Images';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('path')
                    ->label('Image')
                    ->image()
                    // Explicit allow-list rather than the broader image()
                    // default, which would also accept image/svg+xml — SVGs
                    // can embed scripts and are a real XSS vector for
                    // publicly-served product photos.
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(4096)
                    ->disk(app(StorageService::class)->disk())
                    ->directory('products')
                    ->required(),
                TextInput::make('alt_text')
                    ->label('Texte alternatif')
                    ->maxLength(255),
                Toggle::make('is_primary')
                    ->label('Image principale')
                    ->helperText('Activer cette image la définit comme principale et désactive automatiquement l\'ancienne.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('alt_text')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('path')
                    ->label('Aperçu')
                    ->disk(app(StorageService::class)->disk()),
                TextColumn::make('alt_text')
                    ->label('Texte alternatif'),
                IconColumn::make('is_primary')
                    ->label('Principale')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Ordre')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data, RelationManager $livewire): Model {
                        return app(ProductService::class)->addImage(
                            $livewire->getOwnerRecord(),
                            $data['path'],
                            $data['alt_text'] ?? null,
                            (bool) ($data['is_primary'] ?? false),
                        );
                    }),
            ])
            ->recordActions([
                Action::make('setPrimary')
                    ->label('Définir comme principale')
                    ->icon(Heroicon::OutlinedStar)
                    ->color('warning')
                    ->visible(fn ($record) => ! $record->is_primary)
                    ->requiresConfirmation()
                    ->action(fn ($record) => app(ProductService::class)->setPrimaryImage($record)),
                EditAction::make()
                    ->using(fn (Model $record, array $data): Model => app(ProductService::class)->updateImage($record, $data)),
                DeleteAction::make()
                    ->using(function (Model $record): bool {
                        app(ProductService::class)->removeImage($record);

                        return true;
                    }),
            ]);
    }
}
