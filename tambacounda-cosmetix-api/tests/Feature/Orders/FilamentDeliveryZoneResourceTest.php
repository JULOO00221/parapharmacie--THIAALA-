<?php

namespace Tests\Feature\Orders;

use App\Filament\Resources\DeliveryZones\Pages\CreateDeliveryZone;
use App\Filament\Resources\DeliveryZones\Pages\ListDeliveryZones;
use App\Models\DeliveryZone;
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

class FilamentDeliveryZoneResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::findOrCreate('admin', 'web'));
    }

    // --- accès --------------------------------------------------------------

    public function test_admin_can_view_index_and_create_pages(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/delivery-zones')->assertOk();
        $this->get('/admin/delivery-zones/create')->assertOk();
    }

    public function test_admin_can_view_the_edit_page(): void
    {
        $this->actingAs($this->admin);
        $zone = DeliveryZone::create(['name' => 'Zone test', 'fee' => 1000, 'is_active' => true]);

        $this->get("/admin/delivery-zones/{$zone->id}/edit")->assertOk();
    }

    public function test_a_non_admin_cannot_access_delivery_zones(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/admin/delivery-zones')->assertForbidden();
    }

    // --- validation --------------------------------------------------------------

    public function test_admin_can_create_a_zone_through_the_filament_form(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateDeliveryZone::class)
            ->fillForm(['name' => 'Centre-ville', 'fee' => 1500, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('delivery_zones', ['name' => 'Centre-ville', 'fee' => 1500, 'is_active' => true]);
    }

    public function test_name_is_required(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateDeliveryZone::class)
            ->fillForm(['name' => '', 'fee' => 1000])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_name_must_be_unique(): void
    {
        $this->actingAs($this->admin);
        DeliveryZone::create(['name' => 'Centre-ville', 'fee' => 1000, 'is_active' => true]);

        Livewire::test(CreateDeliveryZone::class)
            ->fillForm(['name' => 'Centre-ville', 'fee' => 2000])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique']);
    }

    public function test_fee_cannot_be_negative(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateDeliveryZone::class)
            ->fillForm(['name' => 'Zone test', 'fee' => -100])
            ->call('create')
            ->assertHasFormErrors(['fee']);
    }

    // --- statut --------------------------------------------------------------------

    public function test_toggle_active_action_flips_the_status(): void
    {
        $this->actingAs($this->admin);
        $zone = DeliveryZone::create(['name' => 'Zone test', 'fee' => 1000, 'is_active' => true]);

        Livewire::test(ListDeliveryZones::class)->callTableAction('toggleActive', $zone);

        $this->assertFalse($zone->fresh()->is_active);

        Livewire::test(ListDeliveryZones::class)->callTableAction('toggleActive', $zone->fresh());

        $this->assertTrue($zone->fresh()->is_active);
    }

    // --- historique des commandes ------------------------------------------------------

    /**
     * Désactiver une zone ne doit jamais modifier une commande déjà créée :
     * delivery_fee/delivery_address/is_pickup sont des instantanés stockés
     * directement sur la commande, jamais recalculés depuis la zone.
     */
    public function test_deactivating_a_zone_does_not_alter_historical_orders(): void
    {
        $store = Store::factory()->create(['is_active' => true]);
        $zone = DeliveryZone::create(['name' => 'Zone historique', 'fee' => 1500, 'is_active' => true]);
        $product = Product::factory()->create(['price' => 1000, 'is_active' => true]);
        Stock::create(['product_id' => $product->id, 'store_id' => $store->id, 'quantity_available' => 5, 'quantity_reserved' => 0]);

        $order = app(OrderService::class)->createOrder([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'store_id' => $store->id,
            'customer_name' => 'Awa Diop',
            'customer_phone' => '+221771234567',
            'is_pickup' => false,
            'delivery_zone_id' => $zone->id,
            'delivery_address' => 'Quartier X',
            'payment_method' => 'cash_on_delivery',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $this->actingAs($this->admin);
        Livewire::test(ListDeliveryZones::class)->callTableAction('toggleActive', $zone->fresh());

        $this->assertFalse($zone->fresh()->is_active);

        $order->refresh();
        $this->assertSame($zone->id, $order->delivery_zone_id);
        $this->assertEquals(1500.0, (float) $order->delivery_fee);
        $this->assertSame('Quartier X', $order->delivery_address);
        $this->assertFalse((bool) $order->is_pickup);

        // La commande reste consultable, y compris avec la zone désactivée.
        $this->get("/admin/orders/{$order->id}")->assertOk();
    }

    /**
     * Une zone déjà utilisée par une commande ne peut pas être supprimée —
     * garantie posée au niveau base (orders.delivery_zone_id restrictOnDelete),
     * pas une logique applicative à dupliquer ici.
     */
    public function test_a_zone_used_by_an_order_cannot_be_deleted(): void
    {
        $store = Store::factory()->create(['is_active' => true]);
        $zone = DeliveryZone::create(['name' => 'Zone utilisée', 'fee' => 1500, 'is_active' => true]);
        $product = Product::factory()->create(['price' => 1000, 'is_active' => true]);
        Stock::create(['product_id' => $product->id, 'store_id' => $store->id, 'quantity_available' => 5, 'quantity_reserved' => 0]);

        app(OrderService::class)->createOrder([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'store_id' => $store->id,
            'customer_name' => 'Awa Diop',
            'customer_phone' => '+221771234567',
            'is_pickup' => false,
            'delivery_zone_id' => $zone->id,
            'delivery_address' => 'Quartier X',
            'payment_method' => 'cash_on_delivery',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $zone->delete();
    }
}
