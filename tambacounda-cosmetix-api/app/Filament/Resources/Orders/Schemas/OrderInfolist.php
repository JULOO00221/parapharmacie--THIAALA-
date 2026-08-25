<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

/**
 * Read-only by design — see OrderResource's class doc comment. Every
 * value here is displayed straight from the Order/OrderItem models,
 * never editable through this schema.
 */
class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Commande')
                ->columns(3)
                ->schema([
                    TextEntry::make('order_number')
                        ->label('Numéro')
                        ->copyable(),
                    TextEntry::make('status')
                        ->label('Statut')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => OrderResource::STATUS_LABELS[$state] ?? $state)
                        ->color(fn (string $state) => OrderResource::STATUS_COLORS[$state] ?? 'gray'),
                    TextEntry::make('created_at')
                        ->label('Créée le')
                        ->dateTime(),
                ]),

            Section::make('Client')
                ->columns(3)
                ->schema([
                    TextEntry::make('customer_name')->label('Nom'),
                    TextEntry::make('customer_phone')->label('Téléphone')->copyable(),
                    TextEntry::make('customer_email')->label('Email')->placeholder('—'),
                ]),

            Section::make('Réception')
                ->columns(3)
                ->schema([
                    TextEntry::make('is_pickup')
                        ->label('Mode')
                        ->formatStateUsing(fn (bool $state) => $state ? 'Retrait en boutique' : 'Livraison'),
                    TextEntry::make('store.name')->label('Boutique'),
                    TextEntry::make('deliveryZone.name')->label('Zone de livraison')->placeholder('—'),
                    TextEntry::make('delivery_address')->label('Adresse')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('notes')->label('Notes')->placeholder('—')->columnSpanFull(),
                ]),

            Section::make('Paiement')
                ->columns(2)
                ->schema([
                    TextEntry::make('payment_method')
                        ->label('Moyen')
                        ->formatStateUsing(fn (?string $state) => $state !== null
                            ? (OrderResource::PAYMENT_METHOD_LABELS[$state] ?? $state)
                            : '—'),
                    TextEntry::make('payment_status')
                        ->label('Statut du paiement')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => OrderResource::PAYMENT_STATUS_LABELS[$state] ?? $state)
                        ->color(fn (string $state) => OrderResource::PAYMENT_STATUS_COLORS[$state] ?? 'gray'),
                ]),

            Section::make('Paiements en ligne')
                ->visible(fn ($record) => $record->payments->isNotEmpty())
                ->schema([
                    RepeatableEntry::make('payments')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Fournisseur'),
                            TableColumn::make('Référence interne'),
                            TableColumn::make('Référence fournisseur'),
                            TableColumn::make('Montant'),
                            TableColumn::make('Statut'),
                            TableColumn::make('Payé le'),
                            TableColumn::make('Échec'),
                            TableColumn::make('Expire le'),
                        ])
                        ->schema([
                            TextEntry::make('provider')
                                ->label('Fournisseur')
                                ->formatStateUsing(fn (string $state) => OrderResource::PAYMENT_METHOD_LABELS[$state] ?? $state),
                            TextEntry::make('transaction_id')->label('Référence interne')->copyable(),
                            TextEntry::make('external_reference')->label('Référence fournisseur')->placeholder('—')->copyable(),
                            TextEntry::make('amount')->label('Montant')->money('XOF'),
                            TextEntry::make('status')
                                ->label('Statut')
                                ->badge()
                                ->formatStateUsing(fn (string $state) => OrderResource::PAYMENT_ATTEMPT_STATUS_LABELS[$state] ?? $state)
                                ->color(fn (string $state) => OrderResource::PAYMENT_ATTEMPT_STATUS_COLORS[$state] ?? 'gray'),
                            TextEntry::make('paid_at')->label('Payé le')->dateTime()->placeholder('—'),
                            TextEntry::make('failure_reason')->label('Échec')->placeholder('—'),
                            TextEntry::make('expires_at')->label('Expire le')->dateTime()->placeholder('—'),
                        ]),
                ]),

            Section::make('Articles')
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Produit'),
                            TableColumn::make('SKU'),
                            TableColumn::make('Qté'),
                            TableColumn::make('Prix unitaire'),
                            TableColumn::make('Sous-total'),
                        ])
                        ->schema([
                            TextEntry::make('product_name')->label('Produit'),
                            TextEntry::make('sku')->label('SKU'),
                            TextEntry::make('quantity')->label('Qté'),
                            TextEntry::make('unit_price')->label('Prix unitaire')->money('XOF'),
                            TextEntry::make('subtotal')->label('Sous-total')->money('XOF'),
                        ]),
                ]),

            Section::make('Totaux')
                ->columns(3)
                ->schema([
                    TextEntry::make('subtotal')->label('Sous-total')->money('XOF'),
                    TextEntry::make('delivery_fee')->label('Livraison')->money('XOF'),
                    TextEntry::make('total')->label('Total')->money('XOF')->weight(FontWeight::Bold),
                ]),
        ]);
    }
}
