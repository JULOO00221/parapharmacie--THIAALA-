<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\SendWhatsAppNotification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\WhatsApp\WhatsAppMessageResult;
use App\WhatsApp\WhatsAppProviderInterface;
use App\WhatsApp\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    /**
     * @return array{0: Order, 1: Payment}
     */
    private function createInitiatedWavePayment(): array
    {
        $store = Store::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['price' => 1000, 'is_active' => true]);
        Stock::create(['product_id' => $product->id, 'store_id' => $store->id, 'quantity_available' => 5, 'quantity_reserved' => 0]);

        $order = app(OrderService::class)->createOrder([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'store_id' => $store->id,
            'customer_name' => 'Awa Diop',
            'customer_phone' => '+221771234567',
            'is_pickup' => true,
            'payment_method' => 'wave',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $payment = app(PaymentService::class)->initiate($order, 'wave');

        return [$order, $payment];
    }

    public function test_payment_confirmed_notifies_the_customer(): void
    {
        Queue::fake();
        [$order, $payment] = $this->createInitiatedWavePayment();

        app(PaymentService::class)->markSucceeded($payment);

        Queue::assertPushed(SendWhatsAppNotification::class, fn ($job) => $job->phone === $order->customer_phone
            && $job->template === WhatsAppTemplate::PAYMENT_CONFIRMED->value
            && $job->parameters['order_number'] === $order->order_number);
    }

    public function test_payment_confirmed_also_notifies_order_confirmed_via_the_shared_transition(): void
    {
        Queue::fake();
        [$order, $payment] = $this->createInitiatedWavePayment();

        app(PaymentService::class)->markSucceeded($payment);

        // confirm() lève OrderStatusChanged(to: 'confirmed') — la
        // notification "commande confirmée" n'est pas dupliquée dans
        // PaymentService, elle vient du même chemin que le cash.
        Queue::assertPushed(SendWhatsAppNotification::class, fn ($job) => $job->phone === $order->customer_phone
            && $job->template === WhatsAppTemplate::ORDER_CONFIRMED->value);
    }

    public function test_replaying_markSucceeded_on_an_already_paid_payment_never_notifies_twice(): void
    {
        Queue::fake();
        [, $payment] = $this->createInitiatedWavePayment();

        app(PaymentService::class)->markSucceeded($payment);
        app(PaymentService::class)->markSucceeded($payment->fresh()); // rejoué (webhook dupliqué)

        Queue::assertPushed(
            SendWhatsAppNotification::class,
            fn ($job) => $job->template === WhatsAppTemplate::PAYMENT_CONFIRMED->value,
            1
        );
    }

    public function test_a_failed_payment_never_notifies_payment_confirmed(): void
    {
        Queue::fake();
        [, $payment] = $this->createInitiatedWavePayment();

        app(PaymentService::class)->markFailed($payment, 'insufficient_funds');

        Queue::assertNotPushed(
            SendWhatsAppNotification::class,
            fn ($job) => $job->template === WhatsAppTemplate::PAYMENT_CONFIRMED->value
        );
    }

    public function test_a_whatsapp_provider_failure_never_prevents_payment_confirmation(): void
    {
        Queue::fake();
        $this->app->bind(WhatsAppProviderInterface::class, fn () => new class implements WhatsAppProviderInterface
        {
            public function sendTemplate(string $phone, string $template, array $parameters): WhatsAppMessageResult
            {
                return new WhatsAppMessageResult(success: false, status: 'failed', externalId: null, error: 'simulated failure');
            }
        });

        [$order, $payment] = $this->createInitiatedWavePayment();

        app(PaymentService::class)->markSucceeded($payment);

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }
}
