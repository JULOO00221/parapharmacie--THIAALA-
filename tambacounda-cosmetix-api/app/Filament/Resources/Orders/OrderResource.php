<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * No create/edit form: an order is only ever created by
 * OrderService::createOrder() (checkout) — never hand-entered — and its
 * money/items fields are historical snapshots that must never be
 * hand-edited. The only thing an admin can change here is the status,
 * exclusively through OrderService, via the actions defined in
 * Tables/OrdersTable — see STATUS_TRANSITION_ACTIONS below.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Commandes';

    protected static ?string $modelLabel = 'commande';

    protected static ?string $pluralModelLabel = 'commandes';

    protected static ?string $recordTitleAttribute = 'order_number';

    protected static ?int $navigationSort = 1;

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        'pending' => 'En attente',
        'confirmed' => 'Confirmée',
        'preparing' => 'En préparation',
        'ready' => 'Prête',
        'delivered' => 'Retirée',
        'cancelled' => 'Annulée',
    ];

    /** @var array<string, string> */
    public const STATUS_COLORS = [
        'pending' => 'gray',
        'confirmed' => 'info',
        'preparing' => 'warning',
        'ready' => 'success',
        'delivered' => 'success',
        'cancelled' => 'danger',
    ];

    /** @var array<string, string> */
    public const PAYMENT_STATUS_LABELS = [
        'pending' => 'En attente',
        'paid' => 'Payée',
    ];

    /** @var array<string, string> */
    public const PAYMENT_STATUS_COLORS = [
        'pending' => 'gray',
        'paid' => 'success',
    ];

    /** @var array<string, string> */
    public const PAYMENT_METHOD_LABELS = [
        'cash_in_store' => 'Paiement à la boutique',
        'cash_on_delivery' => 'Paiement à la livraison',
    ];

    /**
     * One entry per possible target status. `method` is the exact
     * OrderService method to call — never a raw ->update(['status' => ...]).
     * Which of these actually show up for a given order is decided at
     * render time from OrderService::allowedTransitions(), not from this
     * list alone, so an outdated entry here can never expose an invalid
     * transition — it would just fail to render.
     *
     * @var array<string, array{method: string, label: string, color: string}>
     */
    public const STATUS_TRANSITION_ACTIONS = [
        'confirmed' => ['method' => 'confirm', 'label' => 'Confirmer', 'color' => 'info'],
        'preparing' => ['method' => 'preparing', 'label' => 'Marquer en préparation', 'color' => 'warning'],
        'ready' => ['method' => 'ready', 'label' => 'Marquer prête', 'color' => 'success'],
        'delivered' => ['method' => 'delivered', 'label' => 'Marquer retirée', 'color' => 'success'],
        'cancelled' => ['method' => 'cancel', 'label' => 'Annuler', 'color' => 'danger'],
    ];

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
