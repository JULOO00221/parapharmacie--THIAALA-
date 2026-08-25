<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The REAL future Wave webhook (POST /api/v1/payments/wave/callback) —
 * distinct from the mock simulator tested in MockWaveWebhookTest. No real
 * Wave call is ever made: these tests only exercise our own signature
 * verification and PaymentService dispatch logic, using a fake secret we
 * control entirely.
 */
class WaveWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.wave.webhook_secret' => self::SECRET]);
    }

    private function createInitiatedPayment(int $quantity = 1, float $unitPrice = 1000.0, int $stockAvailable = 5): array
    {
        $store = Store::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['price' => $unitPrice, 'is_active' => true]);
        $stock = Stock::create(['product_id' => $product->id, 'store_id' => $store->id, 'quantity_available' => $stockAvailable, 'quantity_reserved' => 0]);

        $order = app(OrderService::class)->createOrder([
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
            'store_id' => $store->id,
            'customer_name' => 'Awa Diop',
            'customer_phone' => '+221771234567',
            'is_pickup' => true,
            'payment_method' => 'wave',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $payment = app(PaymentService::class)->initiate($order, 'wave');

        return [$order, $payment, $stock->fresh()];
    }

    private function signedHeader(string $rawBody, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', $timestamp.$rawBody, self::SECRET);

        return "t={$timestamp},v1={$signature}";
    }

    private function completedPayload(Payment $payment, ?string $amount = null, ?string $currency = null): array
    {
        return [
            'id' => 'evt-'.Str::random(10),
            'type' => 'checkout.session.completed',
            'data' => [
                'id' => $payment->external_reference,
                'amount' => $amount ?? (string) $payment->amount,
                'currency' => $currency ?? $payment->currency,
                'payment_status' => 'succeeded',
                'checkout_status' => 'complete',
            ],
        ];
    }

    private function postWebhook(array $payload, ?string $header): \Illuminate\Testing\TestResponse
    {
        $rawBody = json_encode($payload);
        $headers = $header !== null ? ['Wave-Signature' => $header] : [];

        return $this->postJson('/api/v1/payments/wave/callback', $payload, $headers);
    }

    // --- signature ----------------------------------------------------------------

    public function test_missing_signature_is_refused(): void
    {
        [, $payment] = $this->createInitiatedPayment();

        $this->postWebhook($this->completedPayload($payment), null)->assertUnauthorized();
    }

    public function test_invalid_signature_is_refused(): void
    {
        [, $payment] = $this->createInitiatedPayment();
        $payload = $this->completedPayload($payment);

        $this->postWebhook($payload, 't='.time().',v1=deadbeef00000000')->assertUnauthorized();
    }

    public function test_signature_computed_with_the_wrong_secret_is_refused(): void
    {
        [, $payment] = $this->createInitiatedPayment();
        $payload = $this->completedPayload($payment);
        $rawBody = json_encode($payload);
        $timestamp = time();
        $wrongSignature = hash_hmac('sha256', $timestamp.$rawBody, 'not-the-real-secret');

        $this->postWebhook($payload, "t={$timestamp},v1={$wrongSignature}")->assertUnauthorized();
    }

    public function test_stale_timestamp_beyond_five_minutes_is_refused(): void
    {
        [, $payment] = $this->createInitiatedPayment();
        $payload = $this->completedPayload($payment);
        $rawBody = json_encode($payload);
        $staleTimestamp = time() - 600; // 10 minutes

        $this->postWebhook($payload, $this->signedHeader($rawBody, $staleTimestamp))->assertUnauthorized();
        $this->assertSame('processing', $payment->fresh()->status);
    }

    // --- événements valides ---------------------------------------------------------

    public function test_completed_event_with_a_valid_signature_confirms_the_order_and_consumes_stock(): void
    {
        [$order, $payment, $stock] = $this->createInitiatedPayment(quantity: 1, stockAvailable: 5);
        $payload = $this->completedPayload($payment);

        $this->postWebhook($payload, $this->signedHeader(json_encode($payload)))->assertOk();

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(4, $stock->fresh()->quantity_available);
        $this->assertSame(0, $stock->fresh()->quantity_reserved);
    }

    public function test_payment_failed_event_leaves_order_pending(): void
    {
        [$order, $payment] = $this->createInitiatedPayment();
        $payload = [
            'id' => 'evt-'.Str::random(10),
            'type' => 'checkout.session.payment_failed',
            'data' => ['id' => $payment->external_reference],
        ];

        $this->postWebhook($payload, $this->signedHeader(json_encode($payload)))->assertOk();

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_an_unknown_event_type_is_acknowledged_without_side_effects(): void
    {
        [, $payment] = $this->createInitiatedPayment();
        $payload = [
            'id' => 'evt-'.Str::random(10),
            'type' => 'b2b.payment_received',
            'data' => ['id' => $payment->external_reference],
        ];

        $this->postWebhook($payload, $this->signedHeader(json_encode($payload)))->assertOk();

        $this->assertSame('processing', $payment->fresh()->status);
    }

    public function test_a_duplicated_event_never_confirms_or_consumes_stock_twice(): void
    {
        [$order, $payment, $stock] = $this->createInitiatedPayment(quantity: 1, stockAvailable: 5);
        $payload = $this->completedPayload($payment);
        $rawBody = json_encode($payload);
        $header = $this->signedHeader($rawBody);

        $this->postWebhook($payload, $header)->assertOk();
        $availableAfterFirst = $stock->fresh()->quantity_available;

        // Wave garantit pouvoir renvoyer le même événement (retry réseau) —
        // le second appel doit être un pur no-op.
        $this->postWebhook($payload, $header)->assertOk();

        $this->assertSame($availableAfterFirst, $stock->fresh()->quantity_available);
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_an_event_for_an_unknown_payment_is_acknowledged_but_changes_nothing(): void
    {
        $payload = [
            'id' => 'evt-'.Str::random(10),
            'type' => 'checkout.session.completed',
            'data' => ['id' => 'cos-does-not-exist', 'amount' => '1000.00', 'currency' => 'XOF'],
        ];

        $this->postWebhook($payload, $this->signedHeader(json_encode($payload)))->assertOk();

        $this->assertSame(0, Payment::count());
        $this->assertSame(0, Order::count());
    }

    public function test_a_confirmed_amount_that_does_not_match_the_payment_is_rejected(): void
    {
        [$order, $payment] = $this->createInitiatedPayment(unitPrice: 5000);
        $payload = $this->completedPayload($payment, amount: '1.00');

        $this->postWebhook($payload, $this->signedHeader(json_encode($payload)))->assertOk();

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('amount_mismatch', $payment->fresh()->failure_reason);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_a_confirmed_currency_that_does_not_match_xof_is_rejected(): void
    {
        [$order, $payment] = $this->createInitiatedPayment();
        $payload = $this->completedPayload($payment, currency: 'EUR');

        $this->postWebhook($payload, $this->signedHeader(json_encode($payload)))->assertOk();

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('currency_mismatch', $payment->fresh()->failure_reason);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_a_payment_already_marked_paid_stays_untouched_by_a_late_duplicate_completed_event(): void
    {
        [$order, $payment] = $this->createInitiatedPayment();
        $payload = $this->completedPayload($payment);
        $this->postWebhook($payload, $this->signedHeader(json_encode($payload)))->assertOk();
        $paidAtFirst = $payment->fresh()->paid_at;

        $this->postWebhook($payload, $this->signedHeader(json_encode($payload)))->assertOk();

        $this->assertEquals($paidAtFirst, $payment->fresh()->paid_at);
        $this->assertSame('confirmed', $order->fresh()->status);
    }
}
