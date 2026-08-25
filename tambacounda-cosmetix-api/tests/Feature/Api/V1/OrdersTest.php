<?php

namespace Tests\Feature\Api\V1;

use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrdersTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private DeliveryZone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        // Le throttle:5,1 de POST /orders utilise le cache "array" en test
        // (phpunit.xml), qui n'est PAS réinitialisé par RefreshDatabase —
        // seule la base de données l'est. Sans ce flush, les requêtes des
        // tests précédents s'accumuleraient dans le même compteur de débit
        // (même IP simulée pour toute la suite) et feraient échouer des
        // tests sans rapport avec le rate limiting lui-même.
        Cache::flush();

        $this->store = Store::factory()->create(['is_active' => true]);
        $this->zone = DeliveryZone::create(['name' => 'Zone test', 'fee' => 1000, 'is_active' => true]);
    }

    private function productWithStock(float $price, int $available): Product
    {
        $product = Product::factory()->create(['price' => $price, 'is_active' => true]);

        Stock::create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_available' => $available,
            'quantity_reserved' => 0,
        ]);

        return $product;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'items' => [],
            'store_id' => $this->store->id,
            'customer_name' => 'Awa Diop',
            'customer_phone' => '+221771234567',
            'customer_email' => null,
            'is_pickup' => true,
            'payment_method' => 'cash_in_store',
        ], $overrides);
    }

    private function postOrder(array $payload, ?string $idempotencyKey = null)
    {
        return $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid(),
        ])->postJson('/api/v1/orders', $payload);
    }

    // --- 1-2 : création guest / authentifié ------------------------------

    public function test_guest_can_create_an_order(): void
    {
        $product = $this->productWithStock(1000, 5);

        $response = $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]));

        $response->assertCreated();
        $this->assertDatabaseHas('orders', ['order_number' => $response->json('data.order_number'), 'user_id' => null]);
    }

    public function test_authenticated_user_can_create_an_order(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $this->productWithStock(1000, 5);

        $response = $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));

        $response->assertCreated();
        $this->assertDatabaseHas('orders', ['order_number' => $response->json('data.order_number'), 'user_id' => $user->id]);
    }

    // --- 3-7 : validation items/quantité -----------------------------------

    public function test_items_is_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['items']);

        $this->postOrder($payload)->assertStatus(422)->assertJsonValidationErrors('items');
    }

    public function test_items_cannot_be_empty(): void
    {
        $this->postOrder($this->validPayload(['items' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    public function test_product_id_must_be_a_valid_positive_integer(): void
    {
        $this->postOrder($this->validPayload([
            'items' => [['product_id' => 0, 'quantity' => 1]],
        ]))->assertStatus(422)->assertJsonValidationErrors('items.0.product_id');
    }

    public function test_quantity_must_be_at_least_one(): void
    {
        $product = $this->productWithStock(1000, 5);

        $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 0]],
        ]))->assertStatus(422)->assertJsonValidationErrors('items.0.quantity');
    }

    public function test_quantity_above_fifty_is_rejected(): void
    {
        $product = $this->productWithStock(1000, 100);

        $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 51]],
        ]))->assertStatus(422)->assertJsonValidationErrors('items.0.quantity');
    }

    // --- 8-11 : refus métier (OrderService) -----------------------------------

    public function test_inactive_product_is_rejected(): void
    {
        $product = $this->productWithStock(1000, 5);
        $product->update(['is_active' => false]);

        $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]))->assertStatus(422);
    }

    public function test_nonexistent_store_returns_404(): void
    {
        $product = $this->productWithStock(1000, 5);

        $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'store_id' => 999999,
        ]))->assertStatus(404);
    }

    public function test_inactive_store_is_rejected(): void
    {
        $inactiveStore = Store::factory()->create(['is_active' => false]);
        $product = Product::factory()->create(['price' => 1000, 'is_active' => true]);
        Stock::create([
            'product_id' => $product->id,
            'store_id' => $inactiveStore->id,
            'quantity_available' => 5,
            'quantity_reserved' => 0,
        ]);

        $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'store_id' => $inactiveStore->id,
        ]))->assertStatus(422);
    }

    public function test_insufficient_stock_returns_409(): void
    {
        $product = $this->productWithStock(1000, 1);

        $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ]))->assertStatus(409);
    }

    // --- 12-16 : livraison ----------------------------------------------------

    public function test_pickup_order_is_valid(): void
    {
        $product = $this->productWithStock(1000, 5);

        $response = $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'is_pickup' => true,
        ]));

        $response->assertCreated();
        $response->assertJsonPath('data.delivery.is_pickup', true);
        $response->assertJsonPath('data.delivery_fee', '0.00');
    }

    public function test_delivery_order_is_valid(): void
    {
        $product = $this->productWithStock(1000, 5);

        $response = $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'is_pickup' => false,
            'delivery_zone_id' => $this->zone->id,
            'delivery_address' => 'Quartier X, Tambacounda',
        ]));

        $response->assertCreated();
        $response->assertJsonPath('data.delivery.is_pickup', false);
        $response->assertJsonPath('data.delivery.zone.id', $this->zone->id);
    }

    public function test_delivery_without_zone_returns_422(): void
    {
        $product = $this->productWithStock(1000, 5);

        $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'is_pickup' => false,
            'delivery_address' => 'Quartier X',
        ]))->assertStatus(422)->assertJsonValidationErrors('delivery_zone_id');
    }

    public function test_delivery_without_address_returns_422(): void
    {
        $product = $this->productWithStock(1000, 5);

        $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'is_pickup' => false,
            'delivery_zone_id' => $this->zone->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('delivery_address');
    }

    public function test_pickup_with_a_delivery_zone_is_incoherent_and_rejected(): void
    {
        $product = $this->productWithStock(1000, 5);

        $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'is_pickup' => true,
            'delivery_zone_id' => $this->zone->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('delivery_zone_id');
    }

    // --- 17-19 : le client ne peut jamais imposer un prix ---------------------

    public function test_client_supplied_price_total_and_delivery_fee_are_ignored(): void
    {
        $product = $this->productWithStock(1500, 5);

        $response = $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1, 'price' => 1]],
            'subtotal' => 1,
            'total' => 1,
            'delivery_fee' => 999999,
        ]));

        $response->assertCreated();
        $response->assertJsonPath('data.subtotal', '3000.00');
        $response->assertJsonPath('data.total', '3000.00');
        $response->assertJsonPath('data.items.0.unit_price', '1500.00');
    }

    // --- 20-21 : Idempotency-Key ------------------------------------------------

    public function test_idempotency_key_header_is_required(): void
    {
        $product = $this->productWithStock(1000, 5);

        $response = $this->postJson('/api/v1/orders', $this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors('idempotency_key');
    }

    public function test_replaying_the_same_idempotency_key_creates_only_one_order_and_reserves_stock_once(): void
    {
        $product = $this->productWithStock(1000, 5);
        $payload = $this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);
        $key = (string) Str::uuid();

        $first = $this->postOrder($payload, $key);
        $second = $this->postOrder($payload, $key);

        $first->assertCreated();
        $second->assertCreated();
        $this->assertSame($first->json('data.order_number'), $second->json('data.order_number'));
        $this->assertSame(1, Order::count());

        $stock = Stock::where('product_id', $product->id)->first();
        $this->assertSame(2, $stock->quantity_reserved, 'Le stock ne doit être réservé qu\'une seule fois.');
    }

    // --- 22-26 : GET /orders/{orderNumber} --------------------------------------

    public function test_authenticated_owner_can_view_their_order(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $this->productWithStock(1000, 5);

        $created = $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));
        $orderNumber = $created->json('data.order_number');

        $this->getJson("/api/v1/orders/{$orderNumber}")
            ->assertOk()
            ->assertJsonPath('data.order_number', $orderNumber);
    }

    public function test_another_authenticated_user_cannot_view_someone_elses_order(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $product = $this->productWithStock(1000, 5);

        $created = $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));
        $orderNumber = $created->json('data.order_number');

        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);

        $this->getJson("/api/v1/orders/{$orderNumber}")->assertStatus(404);
    }

    public function test_guest_can_view_their_order_with_the_correct_phone(): void
    {
        $product = $this->productWithStock(1000, 5);

        $created = $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer_phone' => '+221701112233',
        ]));
        $orderNumber = $created->json('data.order_number');

        $this->withHeaders(['X-Order-Phone' => '+221701112233'])
            ->getJson("/api/v1/orders/{$orderNumber}")
            ->assertOk()
            ->assertJsonPath('data.order_number', $orderNumber);
    }

    public function test_guest_with_the_wrong_phone_cannot_view_the_order(): void
    {
        $product = $this->productWithStock(1000, 5);

        $created = $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer_phone' => '+221701112233',
        ]));
        $orderNumber = $created->json('data.order_number');

        $this->withHeaders(['X-Order-Phone' => '+221799999999'])
            ->getJson("/api/v1/orders/{$orderNumber}")
            ->assertStatus(404);
    }

    public function test_getting_a_nonexistent_order_returns_404(): void
    {
        $this->getJson('/api/v1/orders/TC-20260101-DOESNOTEXIST')->assertStatus(404);
    }

    // --- 27-29 : GET /orders (liste) -----------------------------------------------

    public function test_listing_orders_requires_authentication(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
    }

    public function test_listing_orders_only_returns_the_authenticated_users_orders(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $product = $this->productWithStock(1000, 10);

        Sanctum::actingAs($userA);
        $this->postOrder($this->validPayload(['items' => [['product_id' => $product->id, 'quantity' => 1]]]));
        $this->postOrder($this->validPayload(['items' => [['product_id' => $product->id, 'quantity' => 1]]]));

        Sanctum::actingAs($userB);
        $this->postOrder($this->validPayload(['items' => [['product_id' => $product->id, 'quantity' => 1]]]));

        Sanctum::actingAs($userA);
        $response = $this->getJson('/api/v1/orders')->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_orders_list_is_paginated(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $this->productWithStock(1000, 10);

        for ($i = 0; $i < 3; $i++) {
            $this->postOrder($this->validPayload(['items' => [['product_id' => $product->id, 'quantity' => 1]]]));
        }

        $response = $this->getJson('/api/v1/orders?per_page=2')->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.per_page'));
    }

    // --- 30-31 : forme de la réponse -------------------------------------------------

    public function test_the_response_never_exposes_sensitive_or_internal_data(): void
    {
        $product = $this->productWithStock(1000, 5);

        $response = $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));

        $json = $response->getContent();
        foreach (['cost_price', 'quantity_reserved', 'alert_threshold', 'idempotency_key', 'user_id', 'exception', 'trace', 'file', 'line'] as $forbidden) {
            $this->assertStringNotContainsString('"'.$forbidden.'"', $json);
        }
    }

    public function test_the_order_response_matches_the_expected_structure(): void
    {
        $product = $this->productWithStock(1000, 5);

        $response = $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));

        $response->assertCreated()->assertJsonStructure([
            'data' => [
                'order_number',
                'status',
                'payment_status',
                'payment_method',
                'customer' => ['name', 'phone', 'email'],
                'store' => ['id', 'name'],
                'delivery' => ['is_pickup', 'zone', 'address'],
                'notes',
                'items' => [['product_id', 'product_name', 'sku', 'quantity', 'unit_price', 'subtotal']],
                'subtotal',
                'delivery_fee',
                'total',
                'currency',
                'created_at',
            ],
        ]);
        $response->assertJsonPath('data.store.id', $this->store->id);
        $response->assertJsonPath('data.store.name', $this->store->name);
    }

    // --- 32 : payment_method --------------------------------------------------------

    public function test_invalid_payment_method_is_rejected(): void
    {
        $product = $this->productWithStock(1000, 5);

        // 'orange_money' n'est pas encore une valeur acceptée par
        // OrderService::PAYMENT_METHODS (intégration hors périmètre pour
        // le moment) — 'wave', lui, est désormais valide (voir Phase 8).
        $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'orange_money',
        ]))->assertStatus(422)->assertJsonValidationErrors('payment_method');
    }

    // --- 33 : rate limiting --------------------------------------------------------

    public function test_post_orders_is_rate_limited_to_five_per_minute(): void
    {
        $product = $this->productWithStock(1000, 100);

        for ($i = 0; $i < 5; $i++) {
            $this->postOrder($this->validPayload([
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]))->assertCreated();
        }

        $this->postOrder($this->validPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]))->assertStatus(429);
    }
}
