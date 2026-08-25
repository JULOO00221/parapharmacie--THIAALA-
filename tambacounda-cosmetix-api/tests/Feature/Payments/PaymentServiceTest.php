<?php

namespace Tests\Feature\Payments;

use App\Exceptions\Payment\InvalidPaymentTransitionException;
use App\Exceptions\Payment\OrderAlreadyPaidException;
use App\Exceptions\Payment\OrderNotPayableException;
use App\Exceptions\Payment\PaymentProviderMismatchException;
use App\Exceptions\Payment\UnsupportedPaymentProviderException;
use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $payments;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payments = app(PaymentService::class);
        $this->orders = app(OrderService::class);

        // Garantit que MockWavePaymentProvider ne fait strictement jamais
        // d'appel réseau : toute tentative ferait échouer le test au lieu
        // d'être silencieusement acceptée.
        Http::preventStrayRequests();
    }

    /**
     * @return array{0: Order, 1: Stock}
     */
    private function createPendingWaveOrder(int $quantity = 1, float $unitPrice = 1000.0, int $stockAvailable = 5): array
    {
        $store = Store::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['price' => $unitPrice, 'is_active' => true]);
        $stock = Stock::create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity_available' => $stockAvailable,
            'quantity_reserved' => 0,
        ]);

        $order = $this->orders->createOrder([
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
            'store_id' => $store->id,
            'customer_name' => 'Awa Diop',
            'customer_phone' => '+221771234567',
            'is_pickup' => true,
            'payment_method' => 'wave',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        return [$order, $stock->fresh()];
    }

    // --- initiate() ------------------------------------------------------------

    public function test_initiate_creates_a_payment_pinned_to_order_total(): void
    {
        [$order] = $this->createPendingWaveOrder(quantity: 2, unitPrice: 1500);

        $payment = $this->payments->initiate($order, 'wave');

        $this->assertSame($order->id, $payment->order_id);
        $this->assertEquals(3000.0, (float) $payment->amount);
        $this->assertEquals((float) $order->total, (float) $payment->amount);
    }

    public function test_initiate_always_uses_xof(): void
    {
        [$order] = $this->createPendingWaveOrder();

        $payment = $this->payments->initiate($order, 'wave');

        $this->assertSame('XOF', $payment->currency);
    }

    public function test_initiate_generates_a_unique_opaque_transaction_id_never_the_auto_incrementing_id(): void
    {
        [$order] = $this->createPendingWaveOrder();

        $payment = $this->payments->initiate($order, 'wave');

        $this->assertMatchesRegularExpression('/^PAY-\d{8}-[A-Z0-9]{10}$/', $payment->transaction_id);
        $this->assertNotSame((string) $payment->id, $payment->transaction_id);
    }

    public function test_initiate_ends_in_processing_with_a_mock_checkout_url_pointing_at_the_frontend_never_wave(): void
    {
        [$order] = $this->createPendingWaveOrder();

        $payment = $this->payments->initiate($order, 'wave');

        $this->assertSame('processing', $payment->status);
        $this->assertNotNull($payment->external_reference);
        $this->assertNotNull($payment->expires_at);
        $this->assertStringStartsWith(
            rtrim((string) config('services.wave.frontend_url'), '/').'/paiement/mock/',
            $payment->metadata['wave_launch_url']
        );
        $this->assertStringNotContainsString('api.wave.com', $payment->metadata['wave_launch_url']);
    }

    public function test_initiate_reuses_the_existing_active_attempt_instead_of_creating_a_second_one(): void
    {
        [$order] = $this->createPendingWaveOrder();

        $first = $this->payments->initiate($order, 'wave');
        $second = $this->payments->initiate($order, 'wave');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, \App\Models\Payment::where('order_id', $order->id)->count());
    }

    public function test_initiate_rejects_a_provider_that_does_not_match_the_order_payment_method(): void
    {
        $store = Store::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['price' => 1000, 'is_active' => true]);
        Stock::create(['product_id' => $product->id, 'store_id' => $store->id, 'quantity_available' => 5, 'quantity_reserved' => 0]);

        $order = $this->orders->createOrder([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'store_id' => $store->id,
            'customer_name' => 'Awa Diop',
            'customer_phone' => '+221771234567',
            'is_pickup' => true,
            'payment_method' => 'cash_in_store',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $this->expectException(PaymentProviderMismatchException::class);
        $this->payments->initiate($order, 'wave');
    }

    public function test_initiate_rejects_unsupported_provider(): void
    {
        [$order] = $this->createPendingWaveOrder();

        $this->expectException(UnsupportedPaymentProviderException::class);
        $this->payments->initiate($order, 'orange_money');
    }

    public function test_initiate_rejects_a_non_pending_order(): void
    {
        [$order] = $this->createPendingWaveOrder();
        $this->orders->cancel($order);

        $this->expectException(OrderNotPayableException::class);
        $this->payments->initiate($order->fresh(), 'wave');
    }

    public function test_initiate_rejects_an_order_whose_payment_status_is_already_paid(): void
    {
        [$order] = $this->createPendingWaveOrder();
        $order->update(['payment_status' => 'paid']);

        $this->expectException(OrderAlreadyPaidException::class);
        $this->payments->initiate($order->fresh(), 'wave');
    }

    // --- succès : stock consommé -----------------------------------------------

    public function test_success_workflow_confirms_the_order_and_consumes_stock(): void
    {
        [$order, $stock] = $this->createPendingWaveOrder(quantity: 2, stockAvailable: 5);
        $payment = $this->payments->initiate($order, 'wave');

        $this->assertSame(2, $stock->fresh()->quantity_reserved);
        $this->assertSame(5, $stock->fresh()->quantity_available);

        $this->payments->markSucceeded($payment);

        $this->assertSame('confirmed', $order->fresh()->status);
        // Régression réelle détectée en vérification Chrome : le succès
        // d'un Payment doit aussi marquer orders.payment_status = 'paid'
        // (comme le flux cash via OrderService::markAsPaid), pas
        // seulement le statut du Payment lui-même.
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
        // confirm() décrémente quantity_available ET remet quantity_reserved à 0.
        $this->assertSame(3, $stock->fresh()->quantity_available);
        $this->assertSame(0, $stock->fresh()->quantity_reserved);
    }

    public function test_mark_succeeded_is_idempotent_never_confirms_or_consumes_stock_twice(): void
    {
        [$order, $stock] = $this->createPendingWaveOrder(quantity: 1, stockAvailable: 5);
        $payment = $this->payments->initiate($order, 'wave');

        $this->payments->markSucceeded($payment);
        $afterFirst = $stock->fresh()->quantity_available;

        $this->payments->markSucceeded($payment->fresh());

        $this->assertSame($afterFirst, $stock->fresh()->quantity_available);
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    // --- échec -------------------------------------------------------------------

    public function test_failure_leaves_the_order_pending_and_stock_reserved(): void
    {
        [$order, $stock] = $this->createPendingWaveOrder(quantity: 1, stockAvailable: 5);
        $payment = $this->payments->initiate($order, 'wave');

        $this->payments->markFailed($payment, 'insufficient_funds');

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('insufficient_funds', $payment->fresh()->failure_reason);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(1, $stock->fresh()->quantity_reserved);
        $this->assertSame(5, $stock->fresh()->quantity_available);
    }

    public function test_mark_failed_is_idempotent(): void
    {
        [$order] = $this->createPendingWaveOrder();
        $payment = $this->payments->initiate($order, 'wave');

        $this->payments->markFailed($payment, 'reason_a');
        $refailed = $this->payments->markFailed($payment->fresh(), 'reason_b');

        $this->assertSame('failed', $refailed->status);
        $this->assertSame('reason_a', $refailed->failure_reason); // le premier motif est conservé, pas écrasé
    }

    public function test_customer_can_retry_after_a_failed_attempt(): void
    {
        [$order] = $this->createPendingWaveOrder();
        $first = $this->payments->initiate($order, 'wave');
        $this->payments->markFailed($first, 'insufficient_funds');

        $retry = $this->payments->initiate($order->fresh(), 'wave');

        $this->assertNotSame($first->id, $retry->id);
        $this->assertSame('processing', $retry->status);
    }

    // --- expiration ----------------------------------------------------------------

    public function test_expiration_cancels_a_pending_order_and_releases_stock(): void
    {
        [$order, $stock] = $this->createPendingWaveOrder(quantity: 1, stockAvailable: 5);
        $payment = $this->payments->initiate($order, 'wave');

        $this->payments->expire($payment);

        $this->assertSame('expired', $payment->fresh()->status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, $stock->fresh()->quantity_reserved);
        $this->assertSame(5, $stock->fresh()->quantity_available);
    }

    public function test_expiration_is_idempotent(): void
    {
        [$order] = $this->createPendingWaveOrder();
        $payment = $this->payments->initiate($order, 'wave');

        $this->payments->expire($payment);
        $again = $this->payments->expire($payment->fresh());

        $this->assertSame('expired', $again->status);
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_expiring_a_stale_failed_retry_never_cancels_an_order_already_confirmed_by_another_successful_attempt(): void
    {
        [$order, $stock] = $this->createPendingWaveOrder(quantity: 1, stockAvailable: 5);

        $successful = $this->payments->initiate($order, 'wave');
        $this->payments->markFailed($successful, 'transient'); // simule une première tentative ratée
        $retry = $this->payments->initiate($order->fresh(), 'wave');
        $this->payments->markSucceeded($retry);

        $this->assertSame('confirmed', $order->fresh()->status);

        // Un job d'expiration en retard sur la toute PREMIÈRE tentative
        // (déjà 'failed', donc plus "active") ne doit rien casser.
        $this->payments->expire($successful->fresh());

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(0, $stock->fresh()->quantity_reserved);
    }

    // --- remboursement --------------------------------------------------------------

    public function test_refund_transitions_a_paid_payment_to_refunded(): void
    {
        [$order] = $this->createPendingWaveOrder();
        $payment = $this->payments->initiate($order, 'wave');
        $this->payments->markSucceeded($payment);

        $refunded = $this->payments->refund($payment->fresh());

        $this->assertSame('refunded', $refunded->status);
    }

    public function test_refund_is_idempotent(): void
    {
        [$order] = $this->createPendingWaveOrder();
        $payment = $this->payments->initiate($order, 'wave');
        $this->payments->markSucceeded($payment);

        $this->payments->refund($payment->fresh());
        $again = $this->payments->refund($payment->fresh());

        $this->assertSame('refunded', $again->status);
    }

    public function test_refund_rejects_a_payment_that_was_never_paid(): void
    {
        [$order] = $this->createPendingWaveOrder();
        $payment = $this->payments->initiate($order, 'wave');

        $this->expectException(InvalidPaymentTransitionException::class);
        $this->payments->refund($payment);
    }

    // --- vérification montant / devise -------------------------------------------

    public function test_mark_succeeded_rejects_an_amount_mismatch_instead_of_confirming(): void
    {
        [$order] = $this->createPendingWaveOrder(unitPrice: 5000);
        $payment = $this->payments->initiate($order, 'wave');

        $result = $this->payments->markSucceeded($payment, confirmedAmount: '500.00', confirmedCurrency: 'XOF');

        $this->assertSame('failed', $result->status);
        $this->assertSame('amount_mismatch', $result->failure_reason);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_mark_succeeded_rejects_a_currency_mismatch_instead_of_confirming(): void
    {
        [$order] = $this->createPendingWaveOrder();
        $payment = $this->payments->initiate($order, 'wave');

        $result = $this->payments->markSucceeded($payment, confirmedAmount: (string) $payment->amount, confirmedCurrency: 'EUR');

        $this->assertSame('failed', $result->status);
        $this->assertSame('currency_mismatch', $result->failure_reason);
        $this->assertSame('pending', $order->fresh()->status);
    }

    // --- commande déjà annulée --------------------------------------------------

    public function test_a_payment_succeeding_for_an_already_cancelled_order_is_refused_never_reactivated(): void
    {
        [$order] = $this->createPendingWaveOrder();
        $payment = $this->payments->initiate($order, 'wave');

        // Une autre voie a fait expirer/annuler la commande entre-temps.
        $this->orders->cancel($order->fresh());

        $result = $this->payments->markSucceeded($payment->fresh());

        $this->assertSame('failed', $result->status);
        $this->assertSame('order_already_cancelled', $result->failure_reason);
        $this->assertSame('cancelled', $order->fresh()->status);
        // Jamais marqué payé malgré la confirmation "reçue".
        $this->assertNotSame('paid', $order->fresh()->payment_status);
    }

    // --- idempotence webhook (callback dupliqué) --------------------------------

    public function test_handle_webhook_resolves_by_external_reference_and_confirms_once(): void
    {
        [$order, $stock] = $this->createPendingWaveOrder(quantity: 1, stockAvailable: 5);
        $payment = $this->payments->initiate($order, 'wave');
        $externalReference = $payment->external_reference;

        $this->payments->handleWebhook('wave', $externalReference, 'succeeded');

        $this->assertSame('confirmed', $order->fresh()->status);
        $consumedOnce = $stock->fresh()->quantity_available;

        // Callback dupliqué : ne doit jamais reconfirmer ni reconsommer le stock.
        $this->payments->handleWebhook('wave', $externalReference, 'succeeded');

        $this->assertSame($consumedOnce, $stock->fresh()->quantity_available);
        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_handle_webhook_throws_for_an_unknown_external_reference(): void
    {
        $this->expectException(\App\Exceptions\Payment\PaymentNotFoundException::class);
        $this->payments->handleWebhook('wave', 'does-not-exist', 'succeeded');
    }
}
