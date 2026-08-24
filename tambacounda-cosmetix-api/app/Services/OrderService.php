<?php

namespace App\Services;

use App\Exceptions\Order\EmptyOrderException;
use App\Exceptions\Order\InsufficientStockException;
use App\Exceptions\Order\InvalidDeliveryZoneException;
use App\Exceptions\Order\InvalidOrderTransitionException;
use App\Exceptions\Order\InvalidPaymentMethodException;
use App\Exceptions\Order\InvalidQuantityException;
use App\Exceptions\Order\ProductNotActiveException;
use App\Exceptions\Order\ProductNotFoundException;
use App\Exceptions\Order\StoreNotActiveException;
use App\Exceptions\Order\StoreNotFoundException;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OrderService
{
    /**
     * Anti-abuse ceiling per line item — not a stock limit (checked
     * separately), just a sanity bound on what a single checkout line can
     * plausibly represent.
     */
    private const MAX_QUANTITY_PER_ITEM = 50;

    /**
     * Only payment methods actually usable today (cash — no online payment
     * provider is integrated yet). The FormRequest layer validates against
     * this same list; OrderService re-checks it independently so it stays
     * the authoritative source of truth even when called outside HTTP.
     */
    public const PAYMENT_METHODS = ['cash_on_delivery', 'cash_in_store'];

    private const ORDER_NUMBER_ATTEMPTS = 5;

    /**
     * Allowed status transitions. delivered/cancelled are terminal — any
     * pair not listed here (e.g. preparing → confirmed, ready → cancelled)
     * is refused.
     */
    private const ALLOWED_TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    /**
     * Creates an order from raw checkout data, never trusting any price,
     * subtotal, delivery fee, total, product name/SKU or stock figure the
     * caller may have included. Idempotent: replaying the same
     * `idempotency_key` returns the original order instead of creating a
     * second one, including when two requests race each other.
     */
    public function createOrder(array $data): Order
    {
        $idempotencyKey = $data['idempotency_key'] ?? null;

        if (empty($idempotencyKey)) {
            throw new RuntimeException('idempotency_key est obligatoire.');
        }

        $existing = Order::where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(fn () => $this->buildOrder($data));
        } catch (UniqueConstraintViolationException $e) {
            // Une autre requête a gagné la course avec la même clé pendant
            // que celle-ci construisait sa transaction : on ne crée jamais
            // une seconde commande, on renvoie celle qui a réellement été
            // créée.
            if ($e->index === 'orders_idempotency_key_unique') {
                return Order::where('idempotency_key', $idempotencyKey)->firstOrFail();
            }

            throw $e;
        }
    }

    private function buildOrder(array $data): Order
    {
        $items = $this->normalizeItems($data['items'] ?? []);

        if ($items === []) {
            throw new EmptyOrderException();
        }

        // Verrouillage des stocks dans un ordre déterministe (product_id
        // croissant) pour réduire le risque de deadlock entre deux
        // commandes concurrentes touchant des produits communs.
        ksort($items);

        $storeId = $data['store_id'] ?? null;
        $store = $storeId !== null ? Store::find($storeId) : null;
        if ($store === null) {
            throw new StoreNotFoundException((int) ($storeId ?? 0));
        }
        if (! $store->is_active) {
            throw new StoreNotActiveException($store->id);
        }

        $paymentMethod = $data['payment_method'] ?? null;
        if (! in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
            throw new InvalidPaymentMethodException((string) $paymentMethod);
        }

        $products = Product::whereIn('id', array_keys($items))->get()->keyBy('id');

        // Passe 1 : valider chaque ligne (existence, activité, quantité)
        // AVANT d'acquérir le moindre verrou sur stocks — évite de tenir
        // des locks pour une commande qui va de toute façon être rejetée.
        foreach ($items as $productId => $quantity) {
            // Un produit soft-deleted n'apparaît jamais dans $products
            // (scope global Eloquent) : il est donc traité exactement
            // comme un produit inexistant, sans logique dédiée.
            $product = $products->get($productId);
            if ($product === null) {
                throw new ProductNotFoundException($productId);
            }

            if (! $product->is_active) {
                throw new ProductNotActiveException($productId);
            }

            if ($quantity < 1 || $quantity > self::MAX_QUANTITY_PER_ITEM) {
                throw new InvalidQuantityException($productId, $quantity, self::MAX_QUANTITY_PER_ITEM);
            }
        }

        // Passe 2 : verrouiller les lignes de stock concernées (ordre
        // product_id croissant déjà garanti par le ksort ci-dessus) et
        // vérifier la disponibilité réelle.
        $stocks = [];
        foreach (array_keys($items) as $productId) {
            $stocks[$productId] = Stock::where('product_id', $productId)
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->first();
        }

        $orderItems = [];
        $subtotal = 0.0;

        foreach ($items as $productId => $quantity) {
            $stock = $stocks[$productId];
            $available = $stock !== null
                ? $stock->quantity_available - $stock->quantity_reserved
                : 0;

            if ($available < $quantity) {
                throw new InsufficientStockException($productId, $quantity, max(0, $available));
            }

            $product = $products->get($productId);
            $unitPrice = (float) $product->price;
            $lineSubtotal = round($unitPrice * $quantity, 2);
            $subtotal = round($subtotal + $lineSubtotal, 2);

            $orderItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'subtotal' => $lineSubtotal,
            ];
        }

        [$isPickup, $deliveryZoneId, $deliveryFee, $deliveryAddress] = $this->resolveDelivery($data);

        $total = round($subtotal + $deliveryFee, 2);

        // Réservation du stock : quantity_reserved augmente, quantity_available
        // reste volontairement inchangé tant que la commande est "pending"
        // (stratégie de réservation validée pour cette phase).
        foreach ($items as $productId => $quantity) {
            $stocks[$productId]->update([
                'quantity_reserved' => $stocks[$productId]->quantity_reserved + $quantity,
            ]);
        }

        $order = Order::create([
            'order_number' => $this->generateOrderNumber(),
            'user_id' => $data['user_id'] ?? null,
            'store_id' => $store->id,
            'delivery_zone_id' => $deliveryZoneId,
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'customer_email' => $data['customer_email'] ?? null,
            'is_pickup' => $isPickup,
            'delivery_address' => $isPickup ? null : $deliveryAddress,
            'notes' => $data['notes'] ?? null,
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'total' => $total,
            'status' => 'pending',
            'payment_method' => $paymentMethod,
            'payment_status' => 'pending',
            'idempotency_key' => $data['idempotency_key'],
        ]);

        $order->items()->createMany($orderItems);

        return $order->load('items');
    }

    /**
     * @return array{0: bool, 1: int|null, 2: float, 3: string|null}
     */
    private function resolveDelivery(array $data): array
    {
        $isPickup = (bool) ($data['is_pickup'] ?? false);

        if ($isPickup) {
            return [true, null, 0.0, null];
        }

        $zoneId = $data['delivery_zone_id'] ?? null;
        if (empty($zoneId)) {
            throw InvalidDeliveryZoneException::required();
        }

        $zone = DeliveryZone::find($zoneId);
        if ($zone === null) {
            throw InvalidDeliveryZoneException::notFound((int) $zoneId);
        }

        if (! $zone->is_active) {
            throw InvalidDeliveryZoneException::notActive($zone->id);
        }

        $address = $data['delivery_address'] ?? null;
        if (empty($address)) {
            throw InvalidDeliveryZoneException::addressRequired();
        }

        return [false, $zone->id, (float) $zone->fee, $address];
    }

    /**
     * Merges duplicate product_id lines by summing quantities, keyed by
     * product_id so callers can force a deterministic ascending processing
     * order (required for the deadlock-avoidance stock locking order).
     *
     * @return array<int, int>
     */
    private function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $normalized[$productId] = ($normalized[$productId] ?? 0) + $quantity;
        }

        return $normalized;
    }

    /**
     * Opaque, non-sequential, hard-to-guess order number — never derived
     * from the auto-incrementing id. Collision is checked before use and
     * retried; a true insert-time collision (astronomically unlikely given
     * the random space) is not separately retried — see the accompanying
     * report for that trade-off.
     */
    private function generateOrderNumber(): string
    {
        for ($attempt = 0; $attempt < self::ORDER_NUMBER_ATTEMPTS; $attempt++) {
            $candidate = sprintf('TC-%s-%s', now()->format('Ymd'), Str::upper(Str::random(10)));

            if (! Order::where('order_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException('Impossible de générer un numéro de commande unique après plusieurs tentatives.');
    }

    /**
     * The statuses `$order` may legally move to from its current status —
     * a read-only view onto ALLOWED_TRANSITIONS so callers (e.g. Filament)
     * can decide which transition buttons to show without duplicating this
     * map themselves.
     *
     * @return list<string>
     */
    public function allowedTransitions(Order $order): array
    {
        return self::ALLOWED_TRANSITIONS[$order->status] ?? [];
    }

    /**
     * Marks payment as received (cash at pickup/delivery — no online
     * payment provider exists yet). Idempotent: already-paid is a silent
     * no-op. Refused on a cancelled order, since there is nothing left to
     * collect payment for.
     */
    public function markAsPaid(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $locked = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($locked->payment_status === 'paid') {
                return $locked;
            }

            if ($locked->status === 'cancelled') {
                throw new InvalidOrderTransitionException('cancelled', 'paid');
            }

            $locked->update(['payment_status' => 'paid']);

            return $locked->refresh();
        });
    }

    public function confirm(Order $order): Order
    {
        return $this->transition($order, 'confirmed', function (Order $locked) {
            foreach ($this->lockOrderStocks($locked) as $productId => $stock) {
                $quantity = $this->quantityFor($locked, $productId);

                $stock->update([
                    'quantity_reserved' => max(0, $stock->quantity_reserved - $quantity),
                    'quantity_available' => max(0, $stock->quantity_available - $quantity),
                ]);
            }
        });
    }

    public function preparing(Order $order): Order
    {
        return $this->transition($order, 'preparing');
    }

    public function ready(Order $order): Order
    {
        return $this->transition($order, 'ready');
    }

    public function delivered(Order $order): Order
    {
        return $this->transition($order, 'delivered');
    }

    public function cancel(Order $order): Order
    {
        return $this->transition($order, 'cancelled', function (Order $locked) {
            $wasPending = $locked->status === 'pending';

            foreach ($this->lockOrderStocks($locked) as $productId => $stock) {
                $quantity = $this->quantityFor($locked, $productId);

                if ($wasPending) {
                    // Le stock n'a jamais quitté quantity_reserved : on le
                    // libère simplement.
                    $stock->update([
                        'quantity_reserved' => max(0, $stock->quantity_reserved - $quantity),
                    ]);
                } else {
                    // confirmed/preparing : quantity_available a déjà été
                    // décrémenté lors de confirm(), il faut donc le
                    // restaurer plutôt que toucher quantity_reserved.
                    $stock->update([
                        'quantity_available' => $stock->quantity_available + $quantity,
                    ]);
                }
            }
        });
    }

    /**
     * Verrouille les lignes de stock concernées par la commande, dans un
     * ordre product_id croissant (même précaution anti-deadlock qu'à la
     * création), et les retourne indexées par product_id. Les lignes dont
     * le produit a été supprimé (product_id NULL, snapshot conservé) sont
     * ignorées : il n'existe plus de stock vivant à ajuster pour elles.
     *
     * @return array<int, Stock>
     */
    private function lockOrderStocks(Order $locked): array
    {
        $productIds = $locked->items
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $stocks = [];
        foreach ($productIds as $productId) {
            $stock = Stock::where('product_id', $productId)
                ->where('store_id', $locked->store_id)
                ->lockForUpdate()
                ->first();

            if ($stock !== null) {
                $stocks[$productId] = $stock;
            }
        }

        return $stocks;
    }

    private function quantityFor(Order $locked, int $productId): int
    {
        return (int) $locked->items
            ->where('product_id', $productId)
            ->sum('quantity');
    }

    /**
     * Verrouille la commande, vérifie que la transition demandée est
     * autorisée depuis son statut courant, applique l'éventuel effet de
     * bord sur le stock, puis met à jour le statut — le tout dans une
     * transaction unique pour qu'une double requête concurrente (double
     * confirmation, double annulation) sur la MÊME commande ne double
     * jamais l'effet stock.
     */
    private function transition(Order $order, string $to, ?callable $sideEffect = null): Order
    {
        return DB::transaction(function () use ($order, $to, $sideEffect) {
            $locked = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();
            $locked->load('items');

            $from = $locked->status;
            $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];

            if (! in_array($to, $allowed, true)) {
                throw new InvalidOrderTransitionException($from, $to);
            }

            if ($sideEffect !== null) {
                $sideEffect($locked);
            }

            $locked->update(['status' => $to]);

            return $locked->refresh();
        });
    }
}
