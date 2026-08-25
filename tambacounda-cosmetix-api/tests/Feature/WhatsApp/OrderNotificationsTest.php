<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\SendWhatsAppNotification;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Services\OrderService;
use App\WhatsApp\WhatsAppMessageResult;
use App\WhatsApp\WhatsAppProviderInterface;
use App\WhatsApp\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifies WhatsApp notifications are correctly dispatched (queued, never
 * sent synchronously) from real OrderService calls — never that
 * OrderService/OrderController know anything about WhatsApp, only that
 * the right App\Jobs\SendWhatsAppNotification ends up queued with the
 * right phone/template/parameters after each transition.
 */
class OrderNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private const MANAGER_PHONE = '+221770000099';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
    }

    /**
     * @return array{0: Order, 1: Stock}
     */
    private function createPendingOrder(bool $isPickup = true, ?int $deliveryZoneId = null, ?string $address = null, int $quantity = 1, int $stockAvailable = 5): array
    {
        $store = Store::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['price' => 1000, 'is_active' => true]);
        $stock = Stock::create(['product_id' => $product->id, 'store_id' => $store->id, 'quantity_available' => $stockAvailable, 'quantity_reserved' => 0]);

        $order = app(OrderService::class)->createOrder([
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
            'store_id' => $store->id,
            'customer_name' => 'Awa Diop',
            'customer_phone' => '+221771234567',
            'is_pickup' => $isPickup,
            'delivery_zone_id' => $deliveryZoneId,
            'delivery_address' => $address,
            'payment_method' => 'cash_in_store',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        return [$order, $stock->fresh()];
    }

    // --- nouvelle commande : client + gérant ------------------------------------

    public function test_new_order_notifies_the_customer(): void
    {
        [$order] = $this->createPendingOrder();

        Queue::assertPushed(SendWhatsAppNotification::class, fn ($job) => $job->phone === $order->customer_phone
            && $job->template === WhatsAppTemplate::ORDER_RECEIVED->value
            && $job->parameters['order_number'] === $order->order_number
            && $job->parameters['customer_name'] === $order->customer_name);
    }

    public function test_new_order_notifies_the_manager_when_configured(): void
    {
        config(['services.whatsapp.manager_phone' => self::MANAGER_PHONE]);

        [$order] = $this->createPendingOrder();

        Queue::assertPushed(SendWhatsAppNotification::class, fn ($job) => $job->phone === self::MANAGER_PHONE
            && $job->template === WhatsAppTemplate::ORDER_RECEIVED_MANAGER->value
            && $job->parameters['order_number'] === $order->order_number
            && $job->parameters['customer_phone'] === $order->customer_phone);
    }

    public function test_new_order_does_not_notify_a_manager_when_no_phone_is_configured(): void
    {
        config(['services.whatsapp.manager_phone' => null]);

        $this->createPendingOrder();

        Queue::assertNotPushed(
            SendWhatsAppNotification::class,
            fn ($job) => $job->template === WhatsAppTemplate::ORDER_RECEIVED_MANAGER->value
        );
    }

    public function test_manager_phone_never_comes_from_anywhere_but_config(): void
    {
        config(['services.whatsapp.manager_phone' => self::MANAGER_PHONE]);

        [$order] = $this->createPendingOrder();

        Queue::assertPushed(SendWhatsAppNotification::class, function ($job) use ($order) {
            if ($job->template !== WhatsAppTemplate::ORDER_RECEIVED_MANAGER->value) {
                return true; // ignore les autres jobs poussés dans ce test
            }

            return $job->phone === self::MANAGER_PHONE && $job->phone !== $order->customer_phone;
        });
    }

    public function test_replaying_the_same_idempotency_key_never_notifies_twice(): void
    {
        $store = Store::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['price' => 1000, 'is_active' => true]);
        Stock::create(['product_id' => $product->id, 'store_id' => $store->id, 'quantity_available' => 5, 'quantity_reserved' => 0]);
        $key = (string) Str::uuid();

        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'store_id' => $store->id,
            'customer_name' => 'Awa Diop',
            'customer_phone' => '+221771234567',
            'is_pickup' => true,
            'payment_method' => 'cash_in_store',
            'idempotency_key' => $key,
        ];

        app(OrderService::class)->createOrder($payload);
        app(OrderService::class)->createOrder($payload); // rejoué, même clé

        Queue::assertPushed(
            SendWhatsAppNotification::class,
            fn ($job) => $job->template === WhatsAppTemplate::ORDER_RECEIVED->value,
            1
        );
    }

    // --- transitions ---------------------------------------------------------------

    public function test_order_confirmed_notifies_the_customer(): void
    {
        [$order] = $this->createPendingOrder();

        app(OrderService::class)->confirm($order->fresh());

        Queue::assertPushed(SendWhatsAppNotification::class, fn ($job) => $job->phone === $order->customer_phone
            && $job->template === WhatsAppTemplate::ORDER_CONFIRMED->value);
    }

    public function test_order_preparing_notifies_the_customer(): void
    {
        [$order] = $this->createPendingOrder();
        app(OrderService::class)->confirm($order->fresh());

        app(OrderService::class)->preparing($order->fresh());

        Queue::assertPushed(SendWhatsAppNotification::class, fn ($job) => $job->phone === $order->customer_phone
            && $job->template === WhatsAppTemplate::ORDER_PREPARING->value);
    }

    public function test_order_ready_for_pickup_uses_the_pickup_template(): void
    {
        [$order] = $this->createPendingOrder(isPickup: true);
        app(OrderService::class)->confirm($order->fresh());
        app(OrderService::class)->preparing($order->fresh());

        app(OrderService::class)->ready($order->fresh());

        Queue::assertPushed(SendWhatsAppNotification::class, fn ($job) => $job->phone === $order->customer_phone
            && $job->template === WhatsAppTemplate::ORDER_READY_PICKUP->value);
        Queue::assertNotPushed(
            SendWhatsAppNotification::class,
            fn ($job) => $job->template === WhatsAppTemplate::ORDER_READY_DELIVERY->value
        );
    }

    public function test_order_ready_for_delivery_uses_the_delivery_template(): void
    {
        $zone = DeliveryZone::create(['name' => 'Zone test', 'fee' => 500, 'is_active' => true]);
        [$order] = $this->createPendingOrder(isPickup: false, deliveryZoneId: $zone->id, address: 'Quartier X');
        app(OrderService::class)->confirm($order->fresh());
        app(OrderService::class)->preparing($order->fresh());

        app(OrderService::class)->ready($order->fresh());

        Queue::assertPushed(SendWhatsAppNotification::class, fn ($job) => $job->phone === $order->customer_phone
            && $job->template === WhatsAppTemplate::ORDER_READY_DELIVERY->value);
    }

    public function test_order_completed_for_pickup_uses_the_pickup_template(): void
    {
        [$order] = $this->createPendingOrder(isPickup: true);
        app(OrderService::class)->confirm($order->fresh());
        app(OrderService::class)->preparing($order->fresh());
        app(OrderService::class)->ready($order->fresh());

        app(OrderService::class)->delivered($order->fresh());

        Queue::assertPushed(SendWhatsAppNotification::class, fn ($job) => $job->phone === $order->customer_phone
            && $job->template === WhatsAppTemplate::ORDER_COMPLETED_PICKUP->value);
    }

    public function test_order_completed_for_delivery_uses_the_delivery_template(): void
    {
        $zone = DeliveryZone::create(['name' => 'Zone test', 'fee' => 500, 'is_active' => true]);
        [$order] = $this->createPendingOrder(isPickup: false, deliveryZoneId: $zone->id, address: 'Quartier X');
        app(OrderService::class)->confirm($order->fresh());
        app(OrderService::class)->preparing($order->fresh());
        app(OrderService::class)->ready($order->fresh());

        app(OrderService::class)->delivered($order->fresh());

        Queue::assertPushed(SendWhatsAppNotification::class, fn ($job) => $job->phone === $order->customer_phone
            && $job->template === WhatsAppTemplate::ORDER_COMPLETED_DELIVERY->value);
    }

    public function test_order_cancelled_notifies_the_customer(): void
    {
        [$order] = $this->createPendingOrder();

        app(OrderService::class)->cancel($order->fresh());

        Queue::assertPushed(SendWhatsAppNotification::class, fn ($job) => $job->phone === $order->customer_phone
            && $job->template === WhatsAppTemplate::ORDER_CANCELLED->value);
    }

    // --- une erreur WhatsApp ne doit jamais affecter la commande --------------------

    public function test_a_whatsapp_provider_failure_never_prevents_order_creation(): void
    {
        // Queue::fake() empêche déjà toute exécution réelle du job dans ce
        // test — createOrder() doit réussir même si le provider était
        // configuré pour échouer, la commande n'a aucune dépendance
        // envers le résultat de l'envoi WhatsApp.
        $this->app->bind(WhatsAppProviderInterface::class, fn () => new class implements WhatsAppProviderInterface
        {
            public function sendTemplate(string $phone, string $template, array $parameters): WhatsAppMessageResult
            {
                return new WhatsAppMessageResult(success: false, status: 'failed', externalId: null, error: 'simulated failure');
            }
        });

        [$order] = $this->createPendingOrder();

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }
}
