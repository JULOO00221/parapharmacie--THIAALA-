<?php

namespace Tests\Feature\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FilamentOrderResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Store $store;

    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::findOrCreate('admin', 'web'));

        $this->store = Store::factory()->create(['is_active' => true]);
        $this->service = app(OrderService::class);
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

    private function createOrder(int $quantity = 1): Order
    {
        $product = $this->productWithStock(1000, 10);

        return $this->service->createOrder([
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
            'store_id' => $this->store->id,
            'customer_name' => 'Awa Diop',
            'customer_phone' => '+221771234567',
            'is_pickup' => true,
            'payment_method' => 'cash_in_store',
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    // --- accès ------------------------------------------------------------------

    public function test_admin_can_view_the_orders_index_page(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/orders')->assertOk();
    }

    public function test_admin_can_view_an_order_page(): void
    {
        $this->actingAs($this->admin);
        $order = $this->createOrder();

        $this->get("/admin/orders/{$order->id}")->assertOk();
    }

    public function test_a_non_admin_cannot_access_the_orders_index(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/admin/orders')->assertForbidden();
    }

    // --- transitions de statut ------------------------------------------------------

    public function test_confirm_action_moves_a_pending_order_to_confirmed_and_updates_stock(): void
    {
        $this->actingAs($this->admin);
        $order = $this->createOrder(quantity: 2);
        $productId = $order->items->first()->product_id;

        Livewire::test(ListOrders::class)
            ->callTableAction('transition_confirmed', $order)
            ->assertHasNoActionErrors();

        $this->assertSame('confirmed', $order->fresh()->status);

        $stock = Stock::where('product_id', $productId)->first();
        $this->assertSame(0, $stock->quantity_reserved);
        $this->assertSame(8, $stock->quantity_available);
    }

    public function test_cancel_action_from_pending_releases_reserved_stock(): void
    {
        $this->actingAs($this->admin);
        $order = $this->createOrder(quantity: 3);
        $productId = $order->items->first()->product_id;

        Livewire::test(ListOrders::class)->callTableAction('transition_cancelled', $order);

        $this->assertSame('cancelled', $order->fresh()->status);
        $stock = Stock::where('product_id', $productId)->first();
        $this->assertSame(0, $stock->quantity_reserved);
        $this->assertSame(10, $stock->quantity_available);
    }

    public function test_only_valid_transitions_are_visible_for_a_pending_order(): void
    {
        $this->actingAs($this->admin);
        $order = $this->createOrder();

        $component = Livewire::test(ListOrders::class);

        $component->assertTableActionVisible('transition_confirmed', $order);
        $component->assertTableActionVisible('transition_cancelled', $order);
        $component->assertTableActionHidden('transition_preparing', $order);
        $component->assertTableActionHidden('transition_ready', $order);
        $component->assertTableActionHidden('transition_delivered', $order);
    }

    public function test_cancel_is_not_available_once_an_order_is_ready(): void
    {
        $this->actingAs($this->admin);
        $order = $this->createOrder();
        $order = $this->service->confirm($order);
        $order = $this->service->preparing($order);
        $order = $this->service->ready($order);

        $component = Livewire::test(ListOrders::class);

        $component->assertTableActionHidden('transition_cancelled', $order);
        $component->assertTableActionVisible('transition_delivered', $order);
    }

    public function test_view_page_exposes_the_same_transition_actions(): void
    {
        $this->actingAs($this->admin);
        $order = $this->createOrder();

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->assertActionVisible('transition_confirmed')
            ->assertActionVisible('transition_cancelled')
            ->assertActionHidden('transition_ready');
    }

    // --- paiement ------------------------------------------------------------------

    public function test_mark_as_paid_action_sets_payment_status(): void
    {
        $this->actingAs($this->admin);
        $order = $this->createOrder();

        Livewire::test(ListOrders::class)
            ->callTableAction('markAsPaid', $order)
            ->assertHasNoActionErrors();

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_mark_as_paid_is_hidden_once_already_paid(): void
    {
        $this->actingAs($this->admin);
        $order = $this->createOrder();
        $this->service->markAsPaid($order);

        Livewire::test(ListOrders::class)
            ->assertTableActionHidden('markAsPaid', $order->fresh());
    }

    // --- filtres / recherche ---------------------------------------------------------

    public function test_orders_can_be_filtered_by_status(): void
    {
        $this->actingAs($this->admin);
        $pending = $this->createOrder();
        $cancelled = $this->service->cancel($this->createOrder());

        Livewire::test(ListOrders::class)
            ->filterTable('status', 'cancelled')
            ->assertCanSeeTableRecords([$cancelled])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    public function test_orders_can_be_searched_by_order_number(): void
    {
        $this->actingAs($this->admin);
        $order = $this->createOrder();
        $other = $this->createOrder();

        Livewire::test(ListOrders::class)
            ->searchTable($order->order_number)
            ->assertCanSeeTableRecords([$order])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // --- affichage ---------------------------------------------------------------------

    public function test_the_order_view_page_displays_its_items(): void
    {
        $this->actingAs($this->admin);
        $order = $this->createOrder();

        $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee($order->customer_name)
            ->assertSee($order->items->first()->product_name);
    }
}
