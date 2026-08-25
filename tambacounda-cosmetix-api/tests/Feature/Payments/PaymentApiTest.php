<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function createWaveOrder(?User $user = null, string $phone = '+221771234567'): array
    {
        $store = Store::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['price' => 1000, 'is_active' => true]);
        $stock = Stock::create(['product_id' => $product->id, 'store_id' => $store->id, 'quantity_available' => 5, 'quantity_reserved' => 0]);

        $order = app(OrderService::class)->createOrder([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'store_id' => $store->id,
            'user_id' => $user?->id,
            'customer_name' => 'Awa Diop',
            'customer_phone' => $phone,
            'is_pickup' => true,
            'payment_method' => 'wave',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        return [$order, $stock];
    }

    // --- POST /orders/{order}/payments (initiation) ------------------------------

    public function test_a_guest_can_initiate_payment_for_their_own_order_by_proving_the_checkout_phone(): void
    {
        [$order] = $this->createWaveOrder(phone: '+221700000000');

        $response = $this->withHeader('X-Order-Phone', '+221700000000')
            ->postJson("/api/v1/orders/{$order->order_number}/payments", ['provider' => 'wave']);

        $response->assertCreated();
        $response->assertJsonStructure([
            'data' => ['transaction_id', 'provider', 'status', 'amount', 'currency', 'checkout_url', 'failure_reason', 'expires_at', 'paid_at', 'created_at'],
        ]);
        $response->assertJsonPath('data.status', 'processing');
        $response->assertJsonPath('data.provider', 'wave');
        $this->assertStringContainsString('/paiement/mock/', $response->json('data.checkout_url'));
    }

    public function test_a_guest_without_the_correct_phone_cannot_initiate_payment(): void
    {
        [$order] = $this->createWaveOrder(phone: '+221700000000');

        $this->postJson("/api/v1/orders/{$order->order_number}/payments", ['provider' => 'wave'])
            ->assertNotFound();
    }

    public function test_an_authenticated_owner_can_initiate_payment_for_their_own_order(): void
    {
        $user = User::factory()->create();
        [$order] = $this->createWaveOrder(user: $user);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->order_number}/payments", ['provider' => 'wave'])
            ->assertCreated();
    }

    public function test_an_authenticated_user_cannot_initiate_payment_for_someone_elses_order(): void
    {
        $owner = User::factory()->create();
        [$order] = $this->createWaveOrder(user: $owner);
        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);

        $this->postJson("/api/v1/orders/{$order->order_number}/payments", ['provider' => 'wave'])
            ->assertNotFound();
    }

    public function test_an_unsupported_provider_is_rejected(): void
    {
        [$order] = $this->createWaveOrder(phone: '+221700000000');

        $this->withHeader('X-Order-Phone', '+221700000000')
            ->postJson("/api/v1/orders/{$order->order_number}/payments", ['provider' => 'orange_money'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('provider');
    }

    public function test_response_never_exposes_internal_id_external_reference_or_raw_metadata(): void
    {
        [$order] = $this->createWaveOrder(phone: '+221700000000');

        $response = $this->withHeader('X-Order-Phone', '+221700000000')
            ->postJson("/api/v1/orders/{$order->order_number}/payments", ['provider' => 'wave']);

        $response->assertJsonMissingPath('data.id');
        $response->assertJsonMissingPath('data.order_id');
        $response->assertJsonMissingPath('data.external_reference');
        $response->assertJsonMissingPath('data.metadata');
    }

    // --- GET /orders/{order}/payments/{transactionId} -----------------------------

    public function test_a_guest_can_check_the_status_of_their_own_payment(): void
    {
        [$order] = $this->createWaveOrder(phone: '+221700000000');
        $init = $this->withHeader('X-Order-Phone', '+221700000000')
            ->postJson("/api/v1/orders/{$order->order_number}/payments", ['provider' => 'wave']);
        $transactionId = $init->json('data.transaction_id');

        $this->withHeader('X-Order-Phone', '+221700000000')
            ->getJson("/api/v1/orders/{$order->order_number}/payments/{$transactionId}")
            ->assertOk()
            ->assertJsonPath('data.transaction_id', $transactionId);
    }

    public function test_a_guest_without_the_correct_phone_cannot_check_payment_status(): void
    {
        [$order] = $this->createWaveOrder(phone: '+221700000000');
        $init = $this->withHeader('X-Order-Phone', '+221700000000')
            ->postJson("/api/v1/orders/{$order->order_number}/payments", ['provider' => 'wave']);
        $transactionId = $init->json('data.transaction_id');

        // withHeader() ci-dessus persiste comme en-tête par défaut pour
        // TOUTES les requêtes suivantes de ce test — il faut le vider
        // explicitement pour que cette requête soit réellement sans preuve.
        $this->flushHeaders();

        $this->getJson("/api/v1/orders/{$order->order_number}/payments/{$transactionId}")
            ->assertNotFound();
    }

    public function test_an_unknown_transaction_id_returns_404(): void
    {
        [$order] = $this->createWaveOrder(phone: '+221700000000');

        $this->withHeader('X-Order-Phone', '+221700000000')
            ->getJson("/api/v1/orders/{$order->order_number}/payments/PAY-DOES-NOT-EXIST")
            ->assertNotFound();
    }

    // --- simulateur mock -----------------------------------------------------------

    public function test_mock_simulate_succeeded_confirms_the_order(): void
    {
        [$order, $stock] = $this->createWaveOrder(phone: '+221700000000');
        $init = $this->withHeader('X-Order-Phone', '+221700000000')
            ->postJson("/api/v1/orders/{$order->order_number}/payments", ['provider' => 'wave']);
        $transactionId = $init->json('data.transaction_id');

        $this->postJson("/api/v1/payments/wave/mock/{$transactionId}/simulate", ['outcome' => 'succeeded'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(4, $stock->fresh()->quantity_available);
    }

    public function test_mock_simulate_failed_leaves_order_pending(): void
    {
        [$order] = $this->createWaveOrder(phone: '+221700000000');
        $init = $this->withHeader('X-Order-Phone', '+221700000000')
            ->postJson("/api/v1/orders/{$order->order_number}/payments", ['provider' => 'wave']);
        $transactionId = $init->json('data.transaction_id');

        $this->postJson("/api/v1/payments/wave/mock/{$transactionId}/simulate", ['outcome' => 'failed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'failed');

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_mock_simulate_expired_cancels_the_order_and_releases_stock(): void
    {
        [$order, $stock] = $this->createWaveOrder(phone: '+221700000000');
        $init = $this->withHeader('X-Order-Phone', '+221700000000')
            ->postJson("/api/v1/orders/{$order->order_number}/payments", ['provider' => 'wave']);
        $transactionId = $init->json('data.transaction_id');

        $this->postJson("/api/v1/payments/wave/mock/{$transactionId}/simulate", ['outcome' => 'expired'])
            ->assertOk()
            ->assertJsonPath('data.status', 'expired');

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, $stock->fresh()->quantity_reserved);
    }

    public function test_mock_simulate_refuses_when_mock_mode_is_disabled_even_if_the_route_is_reached(): void
    {
        [$order] = $this->createWaveOrder(phone: '+221700000000');
        $init = $this->withHeader('X-Order-Phone', '+221700000000')
            ->postJson("/api/v1/orders/{$order->order_number}/payments", ['provider' => 'wave']);
        $transactionId = $init->json('data.transaction_id');

        config(['services.wave.mock' => false]);

        $this->postJson("/api/v1/payments/wave/mock/{$transactionId}/simulate", ['outcome' => 'succeeded'])
            ->assertNotFound();
    }

    public function test_mock_simulate_rejects_an_invalid_outcome(): void
    {
        [$order] = $this->createWaveOrder(phone: '+221700000000');
        $init = $this->withHeader('X-Order-Phone', '+221700000000')
            ->postJson("/api/v1/orders/{$order->order_number}/payments", ['provider' => 'wave']);
        $transactionId = $init->json('data.transaction_id');

        $this->postJson("/api/v1/payments/wave/mock/{$transactionId}/simulate", ['outcome' => 'not_a_real_outcome'])
            ->assertStatus(422);
    }
}
