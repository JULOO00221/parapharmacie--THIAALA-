<?php

namespace Tests\Feature\Orders;

use App\Exceptions\Order\EmptyOrderException;
use App\Exceptions\Order\InsufficientStockException;
use App\Exceptions\Order\InvalidDeliveryZoneException;
use App\Exceptions\Order\InvalidOrderTransitionException;
use App\Exceptions\Order\InvalidQuantityException;
use App\Exceptions\Order\ProductNotActiveException;
use App\Exceptions\Order\ProductNotFoundException;
use App\Exceptions\Order\StoreNotFoundException;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;
use Throwable;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    private Store $store;

    private DeliveryZone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrderService::class);
        $this->store = Store::factory()->create();
        $this->zone = DeliveryZone::create([
            'name' => 'Zone test',
            'fee' => 1000,
            'is_active' => true,
        ]);
    }

    private function productWithStock(float $price, int $available, int $reserved = 0): Product
    {
        $product = Product::factory()->create(['price' => $price, 'is_active' => true]);

        Stock::create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_available' => $available,
            'quantity_reserved' => $reserved,
        ]);

        return $product;
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'store_id' => $this->store->id,
            'customer_name' => 'Awa Diop',
            'customer_phone' => '+221771234567',
            'customer_email' => null,
            'is_pickup' => true,
            'delivery_zone_id' => null,
            'delivery_address' => null,
            'notes' => null,
            'payment_method' => 'cash_in_store',
            'user_id' => null,
            'idempotency_key' => (string) Str::uuid(),
        ], $overrides);
    }

    // --- Création : cas nominaux -----------------------------------------

    public function test_guest_can_create_an_order(): void
    {
        $product = $this->productWithStock(1000, 5);

        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]));

        $this->assertNull($order->user_id);
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertNotEmpty($order->order_number);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => null]);
    }

    public function test_authenticated_user_can_create_an_order(): void
    {
        $user = User::factory()->create();
        $product = $this->productWithStock(1000, 5);

        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'user_id' => $user->id,
        ]));

        $this->assertSame($user->id, $order->user_id);
    }

    public function test_order_number_is_not_sequential_and_is_unique(): void
    {
        $product = $this->productWithStock(1000, 5);

        $first = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));
        $second = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));

        $this->assertNotSame($first->order_number, $second->order_number);
        // Format date + partie aléatoire (jamais un compteur incrémental :
        // deux commandes créées à la suite n'ont aucune raison de produire
        // des numéros consécutifs, contrairement à un id auto-incrémenté).
        $this->assertMatchesRegularExpression('/^TC-\d{8}-[A-Z0-9]{10}$/', $first->order_number);
        $this->assertMatchesRegularExpression('/^TC-\d{8}-[A-Z0-9]{10}$/', $second->order_number);
    }

    // --- Refus produit -----------------------------------------------------

    public function test_it_rejects_a_nonexistent_product(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => 999999, 'quantity' => 1]],
        ]));
    }

    public function test_it_rejects_an_inactive_product(): void
    {
        $product = $this->productWithStock(1000, 5);
        $product->update(['is_active' => false]);

        $this->expectException(ProductNotActiveException::class);

        $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));
    }

    public function test_it_rejects_a_soft_deleted_product(): void
    {
        $product = $this->productWithStock(1000, 5);
        $product->delete();

        $this->expectException(ProductNotFoundException::class);

        $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));
    }

    // --- Quantité ------------------------------------------------------------

    public function test_it_rejects_a_zero_quantity(): void
    {
        $product = $this->productWithStock(1000, 5);

        $this->expectException(InvalidQuantityException::class);

        $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 0]],
        ]));
    }

    public function test_it_rejects_a_quantity_above_the_reasonable_limit(): void
    {
        $product = $this->productWithStock(1000, 100);

        $this->expectException(InvalidQuantityException::class);

        $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 51]],
        ]));
    }

    public function test_it_rejects_an_empty_order(): void
    {
        $this->expectException(EmptyOrderException::class);

        $this->service->createOrder($this->basePayload(['items' => []]));
    }

    public function test_duplicate_product_lines_are_merged_by_quantity(): void
    {
        $product = $this->productWithStock(1000, 5);

        $order = $this->service->createOrder($this->basePayload([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]));

        $this->assertSame(1, $order->items()->count());
        $this->assertSame(3, $order->items()->first()->quantity);
    }

    // --- Boutique --------------------------------------------------------------

    public function test_it_rejects_a_nonexistent_store(): void
    {
        $product = $this->productWithStock(1000, 5);

        $this->expectException(StoreNotFoundException::class);

        $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'store_id' => 999999,
        ]));
    }

    // --- Stock ---------------------------------------------------------------

    public function test_it_rejects_insufficient_stock_and_leaves_stock_untouched(): void
    {
        $product = $this->productWithStock(1000, 2);

        try {
            $this->service->createOrder($this->basePayload([
                'items' => [['product_id' => $product->id, 'quantity' => 3]],
            ]));
            $this->fail('Expected InsufficientStockException.');
        } catch (InsufficientStockException $e) {
            // attendu
        }

        $this->assertDatabaseHas('stocks', [
            'product_id' => $product->id,
            'quantity_available' => 2,
            'quantity_reserved' => 0,
        ]);
        $this->assertSame(0, Order::count());
    }

    public function test_it_accepts_exactly_sufficient_stock(): void
    {
        $product = $this->productWithStock(1000, 3);

        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ]));

        $this->assertDatabaseHas('stocks', [
            'product_id' => $product->id,
            'quantity_available' => 3,
            'quantity_reserved' => 3,
        ]);
        $this->assertSame(1, $order->items()->count());
    }

    public function test_two_sequential_orders_cannot_oversell_the_last_unit(): void
    {
        $product = $this->productWithStock(1000, 1);

        $first = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));
        $this->assertSame('pending', $first->status);

        try {
            $this->service->createOrder($this->basePayload([
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]));
            $this->fail('Expected InsufficientStockException on the second order.');
        } catch (InsufficientStockException $e) {
            // attendu
        }

        $stock = Stock::where('product_id', $product->id)->first();
        $this->assertSame(1, $stock->quantity_reserved);
        $this->assertSame(1, $stock->quantity_available);
        $this->assertGreaterThanOrEqual(0, $stock->quantity_available - $stock->quantity_reserved);
        $this->assertSame(1, Order::count());
    }

    public function test_two_orders_on_the_same_stock_each_reserve_the_exact_quantity(): void
    {
        $product = $this->productWithStock(1000, 5);

        $orderA = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]));
        $orderB = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ]));

        $this->assertNotSame($orderA->id, $orderB->id);

        $stock = Stock::where('product_id', $product->id)->first();
        $this->assertSame(5, $stock->quantity_reserved);
        $this->assertSame(0, $stock->quantity_available - $stock->quantity_reserved);
    }

    public function test_it_creates_a_multi_product_order_with_correct_snapshot_and_totals(): void
    {
        $productA = $this->productWithStock(1000, 5);
        $productB = $this->productWithStock(2000, 5);

        $order = $this->service->createOrder($this->basePayload([
            // Volontairement dans le désordre : le verrouillage doit malgré
            // tout se faire par product_id croissant en interne.
            'items' => [
                ['product_id' => $productB->id, 'quantity' => 2],
                ['product_id' => $productA->id, 'quantity' => 1],
            ],
        ]));

        $this->assertSame(2, $order->items()->count());
        $this->assertEquals(1000 * 1 + 2000 * 2, (float) $order->subtotal);
        $this->assertEquals((float) $order->subtotal, (float) $order->total);

        $stockA = Stock::where('product_id', $productA->id)->first();
        $stockB = Stock::where('product_id', $productB->id)->first();
        $this->assertSame(1, $stockA->quantity_reserved);
        $this->assertSame(2, $stockB->quantity_reserved);
    }

    public function test_a_late_database_failure_rolls_back_stock_reservations_already_written(): void
    {
        $product = $this->productWithStock(1000, 5);

        try {
            $this->service->createOrder($this->basePayload([
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                // Dépasse la limite varchar(255) de customer_name : provoque
                // un vrai échec PostgreSQL après que la réservation de
                // stock a déjà été écrite dans la même transaction.
                'customer_name' => str_repeat('a', 1000),
            ]));
            $this->fail('Expected a database-level failure.');
        } catch (Throwable $e) {
            // attendu, peu importe le type exact — on vérifie l'effet.
        }

        $stock = Stock::where('product_id', $product->id)->first();
        $this->assertSame(5, $stock->quantity_available);
        $this->assertSame(0, $stock->quantity_reserved, 'La réservation doit avoir été annulée par le rollback transactionnel.');
        $this->assertSame(0, Order::count());
    }

    // --- Prix jamais fournis par le client -----------------------------------

    public function test_client_supplied_price_subtotal_total_and_delivery_fee_are_ignored(): void
    {
        $product = $this->productWithStock(1500, 5);

        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'price' => 1, 'unit_price' => 1]],
            'subtotal' => 1,
            'total' => 1,
            'delivery_fee' => 999999,
        ]));

        $this->assertEquals(3000.0, (float) $order->subtotal);
        $this->assertEquals(3000.0, (float) $order->total);
        $this->assertEquals(1500.0, (float) $order->items->first()->unit_price);
    }

    // --- Livraison -------------------------------------------------------------

    public function test_pickup_order_has_no_delivery_zone_or_fee(): void
    {
        $product = $this->productWithStock(1000, 5);

        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'is_pickup' => true,
        ]));

        $this->assertTrue($order->is_pickup);
        $this->assertNull($order->delivery_zone_id);
        $this->assertNull($order->delivery_address);
        $this->assertEquals(0.0, (float) $order->delivery_fee);
    }

    public function test_delivery_order_requires_a_zone_and_uses_its_fee(): void
    {
        $product = $this->productWithStock(1000, 5);

        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'is_pickup' => false,
            'delivery_zone_id' => $this->zone->id,
            'delivery_address' => 'Quartier X, Tambacounda',
        ]));

        $this->assertFalse($order->is_pickup);
        $this->assertSame($this->zone->id, $order->delivery_zone_id);
        $this->assertEquals((float) $this->zone->fee, (float) $order->delivery_fee);
        $this->assertEquals(1000 + (float) $this->zone->fee, (float) $order->total);
    }

    public function test_delivery_order_without_a_zone_is_rejected(): void
    {
        $product = $this->productWithStock(1000, 5);

        $this->expectException(InvalidDeliveryZoneException::class);

        $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'is_pickup' => false,
            'delivery_address' => 'Quartier X',
        ]));
    }

    public function test_delivery_order_without_an_address_is_rejected(): void
    {
        $product = $this->productWithStock(1000, 5);

        $this->expectException(InvalidDeliveryZoneException::class);

        $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'is_pickup' => false,
            'delivery_zone_id' => $this->zone->id,
            'delivery_address' => null,
        ]));
    }

    public function test_it_rejects_a_nonexistent_delivery_zone(): void
    {
        $product = $this->productWithStock(1000, 5);

        $this->expectException(InvalidDeliveryZoneException::class);

        $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'is_pickup' => false,
            'delivery_zone_id' => 999999,
            'delivery_address' => 'Quartier X',
        ]));
    }

    public function test_it_rejects_an_inactive_delivery_zone(): void
    {
        $inactiveZone = DeliveryZone::create(['name' => 'Zone inactive', 'fee' => 500, 'is_active' => false]);
        $product = $this->productWithStock(1000, 5);

        $this->expectException(InvalidDeliveryZoneException::class);

        $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'is_pickup' => false,
            'delivery_zone_id' => $inactiveZone->id,
            'delivery_address' => 'Quartier X',
        ]));
    }

    // --- Snapshot ---------------------------------------------------------------

    public function test_order_item_snapshot_is_independent_of_later_product_changes(): void
    {
        $product = $this->productWithStock(1000, 5);

        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]));

        $item = $order->items()->first();
        $this->assertSame($product->name, $item->product_name);
        $this->assertSame($product->sku, $item->sku);
        $this->assertEquals(1000.0, (float) $item->unit_price);

        $product->update(['name' => 'Nouveau nom', 'sku' => 'NEW-SKU', 'price' => 9999]);

        $item->refresh();
        $this->assertNotSame('Nouveau nom', $item->product_name);
        $this->assertNotSame('NEW-SKU', $item->sku);
        $this->assertEquals(1000.0, (float) $item->unit_price);
    }

    // --- Idempotence --------------------------------------------------------------

    public function test_replaying_the_same_idempotency_key_returns_the_existing_order(): void
    {
        $product = $this->productWithStock(1000, 5);
        $payload = $this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $first = $this->service->createOrder($payload);
        $second = $this->service->createOrder($payload);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::count());

        $stock = Stock::where('product_id', $product->id)->first();
        $this->assertSame(1, $stock->quantity_reserved, 'Le stock ne doit pas être réservé deux fois pour la même clé.');
    }

    /**
     * Une vraie simultanéité (deux processus PostgreSQL distincts arrivant
     * exactement en même temps) n'est pas reproductible de façon fiable
     * dans un test PHPUnit synchrone mono-connexion — voir le rapport. Ce
     * test prouve à la place que le filet de sécurité réel (la contrainte
     * UNIQUE PostgreSQL sur idempotency_key) fonctionne indépendamment de
     * la vérification applicative préalable de createOrder(), en appelant
     * directement buildOrder() — la méthode interne qui n'a pas cette
     * vérification — avec une clé déjà utilisée. C'est exactement ce que
     * createOrder() intercepte et transforme en "renvoyer la commande
     * existante" dans une vraie course.
     */
    public function test_the_idempotency_key_is_protected_by_a_real_database_constraint(): void
    {
        $product = $this->productWithStock(1000, 5);
        $key = (string) Str::uuid();

        $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'idempotency_key' => $key,
        ]));

        $reflection = new ReflectionMethod(OrderService::class, 'buildOrder');
        $reflection->setAccessible(true);

        $this->expectException(UniqueConstraintViolationException::class);

        $reflection->invoke($this->service, $this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'idempotency_key' => $key,
        ]));
    }

    // --- Transitions valides ------------------------------------------------------

    public function test_the_full_valid_transition_chain_succeeds_and_moves_stock_correctly(): void
    {
        $product = $this->productWithStock(1000, 5);

        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]));

        $freshStock = fn () => Stock::where('product_id', $product->id)->first();

        $this->assertSame(5, $freshStock()->quantity_available);
        $this->assertSame(2, $freshStock()->quantity_reserved);

        $order = $this->service->confirm($order);
        $this->assertSame('confirmed', $order->status);
        $this->assertSame(3, $freshStock()->quantity_available);
        $this->assertSame(0, $freshStock()->quantity_reserved);

        $order = $this->service->preparing($order);
        $this->assertSame('preparing', $order->status);

        $order = $this->service->ready($order);
        $this->assertSame('ready', $order->status);

        $order = $this->service->delivered($order);
        $this->assertSame('delivered', $order->status);

        // Le stock n'est plus retouché après confirm().
        $this->assertSame(3, $freshStock()->quantity_available);
        $this->assertSame(0, $freshStock()->quantity_reserved);
    }

    // --- Transitions invalides ------------------------------------------------------

    public function test_delivered_to_cancelled_is_rejected(): void
    {
        $product = $this->productWithStock(1000, 5);
        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));
        $order = $this->service->confirm($order);
        $order = $this->service->preparing($order);
        $order = $this->service->ready($order);
        $order = $this->service->delivered($order);

        $this->expectException(InvalidOrderTransitionException::class);
        $this->service->cancel($order);
    }

    public function test_ready_to_cancelled_is_rejected(): void
    {
        $product = $this->productWithStock(1000, 5);
        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));
        $order = $this->service->confirm($order);
        $order = $this->service->preparing($order);
        $order = $this->service->ready($order);

        $this->expectException(InvalidOrderTransitionException::class);
        $this->service->cancel($order);
    }

    public function test_pending_to_delivered_is_rejected(): void
    {
        $product = $this->productWithStock(1000, 5);
        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));

        $this->expectException(InvalidOrderTransitionException::class);
        $this->service->delivered($order);
    }

    public function test_confirmed_to_delivered_is_rejected(): void
    {
        $product = $this->productWithStock(1000, 5);
        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));
        $order = $this->service->confirm($order);

        $this->expectException(InvalidOrderTransitionException::class);
        $this->service->delivered($order);
    }

    public function test_preparing_to_confirmed_is_rejected(): void
    {
        $product = $this->productWithStock(1000, 5);
        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));
        $order = $this->service->confirm($order);
        $order = $this->service->preparing($order);

        $this->expectException(InvalidOrderTransitionException::class);
        $this->service->confirm($order);
    }

    // --- Annulation et libération du stock -------------------------------------------

    public function test_cancelling_a_pending_order_releases_the_reserved_stock(): void
    {
        $product = $this->productWithStock(1000, 5);
        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]));

        $cancelled = $this->service->cancel($order);

        $this->assertSame('cancelled', $cancelled->status);
        $stock = Stock::where('product_id', $product->id)->first();
        $this->assertSame(5, $stock->quantity_available);
        $this->assertSame(0, $stock->quantity_reserved);
    }

    public function test_cancelling_a_confirmed_order_restores_available_stock(): void
    {
        $product = $this->productWithStock(1000, 5);
        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]));
        $order = $this->service->confirm($order);

        $stockBefore = Stock::where('product_id', $product->id)->first();
        $this->assertSame(3, $stockBefore->quantity_available);
        $this->assertSame(0, $stockBefore->quantity_reserved);

        $cancelled = $this->service->cancel($order);

        $this->assertSame('cancelled', $cancelled->status);
        $stock = Stock::where('product_id', $product->id)->first();
        $this->assertSame(5, $stock->quantity_available);
        $this->assertSame(0, $stock->quantity_reserved);
    }

    public function test_cancelling_a_preparing_order_restores_available_stock(): void
    {
        $product = $this->productWithStock(1000, 5);
        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));
        $order = $this->service->confirm($order);
        $order = $this->service->preparing($order);

        $cancelled = $this->service->cancel($order);

        $this->assertSame('cancelled', $cancelled->status);
        $stock = Stock::where('product_id', $product->id)->first();
        $this->assertSame(5, $stock->quantity_available);
        $this->assertSame(0, $stock->quantity_reserved);
    }

    public function test_order_item_count_is_preserved_after_cancellation(): void
    {
        $product = $this->productWithStock(1000, 5);
        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]));

        $this->service->cancel($order);

        $this->assertSame(1, OrderItem::where('order_id', $order->id)->count());
    }

    // --- allowedTransitions() ----------------------------------------------------

    public function test_allowed_transitions_matches_the_current_status(): void
    {
        $product = $this->productWithStock(1000, 5);
        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));

        $this->assertSame(['confirmed', 'cancelled'], $this->service->allowedTransitions($order));

        $order = $this->service->confirm($order);
        $this->assertSame(['preparing', 'cancelled'], $this->service->allowedTransitions($order));
    }

    public function test_allowed_transitions_is_empty_for_a_terminal_status(): void
    {
        $product = $this->productWithStock(1000, 5);
        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));

        $cancelled = $this->service->cancel($order);

        $this->assertSame([], $this->service->allowedTransitions($cancelled));
    }

    // --- markAsPaid() --------------------------------------------------------------

    public function test_marking_a_pending_order_as_paid_works(): void
    {
        $product = $this->productWithStock(1000, 5);
        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));

        $paid = $this->service->markAsPaid($order);

        $this->assertSame('paid', $paid->payment_status);
        // Le statut de la commande n'est pas affecté par le paiement.
        $this->assertSame('pending', $paid->status);
    }

    public function test_marking_an_already_paid_order_as_paid_is_idempotent(): void
    {
        $product = $this->productWithStock(1000, 5);
        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));

        $this->service->markAsPaid($order);
        $paidAgain = $this->service->markAsPaid($order->fresh());

        $this->assertSame('paid', $paidAgain->payment_status);
    }

    public function test_marking_a_cancelled_order_as_paid_is_rejected(): void
    {
        $product = $this->productWithStock(1000, 5);
        $order = $this->service->createOrder($this->basePayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));
        $cancelled = $this->service->cancel($order);

        $this->expectException(InvalidOrderTransitionException::class);
        $this->service->markAsPaid($cancelled);
    }
}
