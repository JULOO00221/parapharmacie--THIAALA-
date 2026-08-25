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
 * php artisan payments:expire-stale — the sole automatic trigger for
 * PaymentService::expire() (see routes/console.php, scheduled
 * everyMinute()). Never creates any order itself; only acts on rows
 * already present, exactly like PaymentService::expire() would if called
 * directly — these tests verify the command's *selection* logic
 * (which rows it touches) and its resilience, not expire()'s own
 * behaviour, which is already covered by PaymentServiceTest.
 */
class ExpireStalePaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    /**
     * @return array{0: Order, 1: Payment, 2: Stock}
     */
    private function createInitiatedPayment(int $quantity = 1, int $stockAvailable = 5): array
    {
        $store = Store::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['price' => 1000, 'is_active' => true]);
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

    public function test_expires_a_stale_pending_payment_cancels_the_order_and_releases_stock(): void
    {
        [$order, $payment, $stock] = $this->createInitiatedPayment(quantity: 1, stockAvailable: 5);
        $payment->update(['expires_at' => now()->subMinute()]);

        $this->artisan('payments:expire-stale')->assertSuccessful();

        $this->assertSame('expired', $payment->fresh()->status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, $stock->fresh()->quantity_reserved);
        $this->assertSame(5, $stock->fresh()->quantity_available);
    }

    public function test_does_not_touch_a_payment_that_has_not_expired_yet(): void
    {
        [$order, $payment] = $this->createInitiatedPayment();
        $payment->update(['expires_at' => now()->addMinutes(10)]);

        $this->artisan('payments:expire-stale')->assertSuccessful();

        $this->assertSame('processing', $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_does_not_touch_a_payment_with_no_expiration_set(): void
    {
        [, $payment] = $this->createInitiatedPayment();
        $payment->update(['expires_at' => null]);

        $this->artisan('payments:expire-stale')->assertSuccessful();

        $this->assertSame('processing', $payment->fresh()->status);
    }

    public function test_never_touches_an_already_paid_payment_even_with_a_past_expiration(): void
    {
        [$order, $payment] = $this->createInitiatedPayment();
        app(PaymentService::class)->markSucceeded($payment);
        $payment->update(['expires_at' => now()->subDay()]);

        $this->artisan('payments:expire-stale')->assertSuccessful();

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_never_touches_an_already_failed_payment(): void
    {
        [, $payment] = $this->createInitiatedPayment();
        app(PaymentService::class)->markFailed($payment, 'insufficient_funds');
        $payment->update(['expires_at' => now()->subDay()]);

        $this->artisan('payments:expire-stale')->assertSuccessful();

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('insufficient_funds', $payment->fresh()->failure_reason);
    }

    public function test_processes_multiple_stale_payments_independently(): void
    {
        [$orderA, $paymentA, $stockA] = $this->createInitiatedPayment(quantity: 1, stockAvailable: 5);
        $paymentA->update(['expires_at' => now()->subMinute()]);

        [$orderB, $paymentB, $stockB] = $this->createInitiatedPayment(quantity: 2, stockAvailable: 5);
        $paymentB->update(['expires_at' => now()->subMinute()]);

        $this->artisan('payments:expire-stale')->assertSuccessful();

        $this->assertSame('expired', $paymentA->fresh()->status);
        $this->assertSame('cancelled', $orderA->fresh()->status);
        $this->assertSame(0, $stockA->fresh()->quantity_reserved);

        $this->assertSame('expired', $paymentB->fresh()->status);
        $this->assertSame('cancelled', $orderB->fresh()->status);
        $this->assertSame(0, $stockB->fresh()->quantity_reserved);
    }

    public function test_running_it_twice_is_idempotent(): void
    {
        [$order, $payment, $stock] = $this->createInitiatedPayment(quantity: 1, stockAvailable: 5);
        $payment->update(['expires_at' => now()->subMinute()]);

        $this->artisan('payments:expire-stale')->assertSuccessful();
        $availableAfterFirst = $stock->fresh()->quantity_available;

        $this->artisan('payments:expire-stale')->assertSuccessful();

        $this->assertSame('expired', $payment->fresh()->status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame($availableAfterFirst, $stock->fresh()->quantity_available);
    }

    public function test_a_stale_payment_that_is_already_terminal_never_re_cancels_an_order_confirmed_by_a_different_successful_attempt(): void
    {
        [$order, $firstAttempt, $stock] = $this->createInitiatedPayment(quantity: 1, stockAvailable: 5);
        // Une tentative active bloque toute nouvelle initiation (PaymentService::initiate
        // réutilise l'existante) — il faut donc d'abord la clôturer avant
        // qu'une seconde tentative distincte puisse exister.
        app(PaymentService::class)->markFailed($firstAttempt, 'transient');
        $firstAttempt->update(['expires_at' => now()->subMinute()]); // arrive plus tard qu'attendu, déjà terminal

        $retry = app(PaymentService::class)->initiate($order->fresh(), 'wave');
        app(PaymentService::class)->markSucceeded($retry);

        $this->assertSame('confirmed', $order->fresh()->status);

        $this->artisan('payments:expire-stale')->assertSuccessful();

        // La première tentative reste 'failed' (terminale) — le job ne
        // touche que pending/processing, jamais un statut déjà terminal.
        $this->assertSame('failed', $firstAttempt->fresh()->status);
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(0, $stock->fresh()->quantity_reserved);
    }

    public function test_never_creates_any_order_or_payment(): void
    {
        [, $payment] = $this->createInitiatedPayment();
        $payment->update(['expires_at' => now()->subMinute()]);

        $ordersBefore = Order::count();
        $paymentsBefore = Payment::count();

        $this->artisan('payments:expire-stale')->assertSuccessful();

        $this->assertSame($ordersBefore, Order::count());
        $this->assertSame($paymentsBefore, Payment::count());
    }
}
