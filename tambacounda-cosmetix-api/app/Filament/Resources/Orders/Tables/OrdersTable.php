<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Exceptions\Order\InvalidOrderTransitionException;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_number')
                    ->label('Numéro')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('customer_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_phone')
                    ->label('Téléphone')
                    ->searchable(),
                TextColumn::make('store.name')
                    ->label('Boutique')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => OrderResource::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => OrderResource::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('payment_status')
                    ->label('Paiement')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => OrderResource::PAYMENT_STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => OrderResource::PAYMENT_STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('XOF')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(OrderResource::STATUS_LABELS),
                SelectFilter::make('payment_status')
                    ->label('Paiement')
                    ->options(OrderResource::PAYMENT_STATUS_LABELS),
                SelectFilter::make('store_id')
                    ->label('Boutique')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                ...self::transitionActions(),
                self::markAsPaidAction(),
            ]);
    }

    /**
     * One Action per possible target status (OrderResource::STATUS_TRANSITION_ACTIONS),
     * each visible ONLY when OrderService::allowedTransitions() actually
     * allows it for that specific record — the source of truth for which
     * buttons appear is OrderService, never this list on its own.
     *
     * @return array<int, Action>
     */
    public static function transitionActions(): array
    {
        return collect(OrderResource::STATUS_TRANSITION_ACTIONS)
            ->map(function (array $config, string $targetStatus) {
                return Action::make('transition_'.$targetStatus)
                    ->label($config['label'])
                    ->color($config['color'])
                    ->requiresConfirmation()
                    ->visible(
                        fn (Order $record): bool => in_array(
                            $targetStatus,
                            app(OrderService::class)->allowedTransitions($record),
                            true
                        )
                    )
                    ->action(function (Order $record) use ($config) {
                        try {
                            app(OrderService::class)->{$config['method']}($record);

                            Notification::make()
                                ->title('Statut mis à jour')
                                ->success()
                                ->send();
                        } catch (InvalidOrderTransitionException $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    });
            })
            ->values()
            ->all();
    }

    public static function markAsPaidAction(): Action
    {
        return Action::make('markAsPaid')
            ->label('Marquer payée')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Order $record): bool => $record->payment_status !== 'paid' && $record->status !== 'cancelled')
            ->action(function (Order $record) {
                app(OrderService::class)->markAsPaid($record);

                Notification::make()
                    ->title('Paiement enregistré')
                    ->success()
                    ->send();
            });
    }
}
