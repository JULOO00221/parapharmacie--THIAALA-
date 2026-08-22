<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use App\Models\Tag;
use App\Services\ProductService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Produit')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Informations générales')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nom')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (string $operation, $state, callable $set) {
                                        if ($operation === 'create') {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),
                                TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(Product::class, 'slug', ignoreRecord: true),
                                TextInput::make('sku')
                                    ->label('SKU')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(Product::class, 'sku', ignoreRecord: true),
                                TextInput::make('barcode')
                                    ->label('Code-barres')
                                    ->maxLength(255)
                                    ->unique(Product::class, 'barcode', ignoreRecord: true),
                                Select::make('category_id')
                                    ->label('Catégorie')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('brand_id')
                                    ->label('Marque')
                                    ->relationship('brand', 'name')
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('short_description')
                                    ->label('Description courte')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Textarea::make('description')
                                    ->label('Description complète')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Tab::make('Tarification')
                            ->schema([
                                TextInput::make('price')
                                    ->label('Prix de vente')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('FCFA')
                                    ->live(onBlur: true),
                                TextInput::make('cost_price')
                                    ->label('Prix d\'achat')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('FCFA')
                                    ->live(onBlur: true),
                                TextInput::make('compare_at_price')
                                    ->label('Prix de référence / barré')
                                    ->helperText('Doit être supérieur ou égal au prix de vente.')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('FCFA')
                                    ->gte('price'),
                                TextInput::make('tax_rate')
                                    ->label('Taux de TVA (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('%'),
                                TextEntry::make('margin')
                                    ->label('Marge estimée')
                                    ->columnSpanFull()
                                    ->state(function (callable $get) {
                                        $margin = app(ProductService::class)->calculateMargin(
                                            new Product([
                                                'price' => $get('price'),
                                                'cost_price' => $get('cost_price'),
                                            ])
                                        );

                                        if ($margin === null) {
                                            return 'Renseignez le prix d\'achat pour calculer la marge.';
                                        }

                                        return sprintf(
                                            '%.2f FCFA (taux de marque : %s%%, taux de marge : %s%%)',
                                            $margin['amount'],
                                            $margin['markup_rate'] ?? '—',
                                            $margin['margin_rate'] ?? '—',
                                        );
                                    }),
                            ])
                            ->columns(2),

                        Tab::make('Catalogue')
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Actif')
                                    ->default(true),
                                Toggle::make('is_featured')
                                    ->label('Mis en avant'),
                                Toggle::make('requires_prescription')
                                    ->label('Nécessite une ordonnance'),
                                TextInput::make('sort_order')
                                    ->label('Ordre d\'affichage')
                                    ->required()
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('weight')
                                    ->label('Poids (kg)')
                                    ->numeric()
                                    ->minValue(0),
                            ])
                            ->columns(2),

                        Tab::make('Tags')
                            ->schema([
                                Select::make('tags')
                                    ->label('Tags')
                                    ->relationship('tags', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Nom')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                                        TextInput::make('slug')
                                            ->label('Slug')
                                            ->required()
                                            ->unique(Tag::class, 'slug'),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
