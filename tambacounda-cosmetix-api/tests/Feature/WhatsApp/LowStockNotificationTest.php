<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\SendWhatsAppNotification;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\WhatsApp\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * StockObserver is the sole trigger — these tests exercise plain Eloquent
 * mutations (mirroring every real mutation path: OrderService, Filament
 * admin edits, product creation) rather than going through OrderService,
 * since the observer must react identically regardless of which of those
 * paths touched the row.
 */
class LowStockNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const MANAGER_PHONE = '+221770000099';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
        config(['services.whatsapp.manager_phone' => self::MANAGER_PHONE]);
    }

    private function createStock(int $available, int $reserved, ?int $threshold): Stock
    {
        $store = Store::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['name' => 'Crème apaisante', 'sku' => 'TC-9999', 'is_active' => true]);

        return Stock::create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity_available' => $available,
            'quantity_reserved' => $reserved,
            'alert_threshold' => $threshold,
        ]);
    }

    // --- franchissement réel ---------------------------------------------------------

    public function test_crossing_below_the_threshold_notifies_the_manager(): void
    {
        $stock = $this->createStock(available: 10, reserved: 0, threshold: 5);

        $stock->update(['quantity_available' => 3]); // vendable 3 <= seuil 5

        Queue::assertPushed(SendWhatsAppNotification::class, fn ($job) => $job->phone === self::MANAGER_PHONE
            && $job->template === WhatsAppTemplate::LOW_STOCK->value
            && $job->parameters['sku'] === 'TC-9999'
            && $job->parameters['quantity'] === '3'
            && $job->parameters['threshold'] === '5');
    }

    public function test_uses_sellable_stock_not_raw_quantity_available(): void
    {
        $stock = $this->createStock(available: 10, reserved: 0, threshold: 5);

        // quantity_available reste au-dessus du seuil, mais le vendable
        // (10-6=4) passe sous le seuil à cause de la réservation.
        $stock->update(['quantity_reserved' => 6]);

        Queue::assertPushed(SendWhatsAppNotification::class, fn ($job) => $job->template === WhatsAppTemplate::LOW_STOCK->value
            && $job->parameters['quantity'] === '4');
    }

    public function test_no_notification_when_the_threshold_is_not_reached(): void
    {
        $stock = $this->createStock(available: 10, reserved: 0, threshold: 5);

        $stock->update(['quantity_available' => 8]); // vendable 8 > seuil 5

        Queue::assertNotPushed(SendWhatsAppNotification::class);
    }

    public function test_no_notification_when_no_threshold_is_configured(): void
    {
        $stock = $this->createStock(available: 10, reserved: 0, threshold: null);

        $stock->update(['quantity_available' => 0]);

        Queue::assertNotPushed(SendWhatsAppNotification::class);
    }

    public function test_a_stock_created_already_below_threshold_notifies_immediately(): void
    {
        $this->createStock(available: 2, reserved: 0, threshold: 5);

        Queue::assertPushed(SendWhatsAppNotification::class, fn ($job) => $job->template === WhatsAppTemplate::LOW_STOCK->value);
    }

    // --- pas de doublon tant que le stock reste bas ---------------------------------

    public function test_staying_below_threshold_never_re_notifies(): void
    {
        $stock = $this->createStock(available: 10, reserved: 0, threshold: 5);
        $stock->update(['quantity_available' => 3]); // franchissement 1

        $stock->update(['quantity_available' => 2]); // toujours bas, pas un nouveau franchissement
        $stock->update(['quantity_available' => 1]); // toujours bas

        Queue::assertPushed(SendWhatsAppNotification::class, fn ($job) => $job->template === WhatsAppTemplate::LOW_STOCK->value, 1);
    }

    // --- retour au-dessus du seuil puis nouveau franchissement -----------------------

    public function test_a_new_crossing_after_recovering_above_threshold_notifies_again(): void
    {
        $stock = $this->createStock(available: 10, reserved: 0, threshold: 5);
        $stock->update(['quantity_available' => 3]); // 1er franchissement

        $stock->update(['quantity_available' => 20]); // retour largement au-dessus du seuil

        $stock->update(['quantity_available' => 4]); // 2e franchissement, distinct du premier

        Queue::assertPushed(SendWhatsAppNotification::class, fn ($job) => $job->template === WhatsAppTemplate::LOW_STOCK->value, 2);
    }

    public function test_no_notification_when_no_manager_phone_is_configured(): void
    {
        config(['services.whatsapp.manager_phone' => null]);
        $stock = $this->createStock(available: 10, reserved: 0, threshold: 5);

        $stock->update(['quantity_available' => 1]);

        Queue::assertNotPushed(SendWhatsAppNotification::class);
    }

    public function test_editing_an_unrelated_field_does_not_trigger_a_notification(): void
    {
        $stock = $this->createStock(available: 3, reserved: 0, threshold: 5); // déjà bas dès la création
        Queue::fake(); // ignore le job de création, on ne teste que l'update qui suit

        $stock->touch(); // sauvegarde sans changement réel de quantité/seuil

        Queue::assertNotPushed(SendWhatsAppNotification::class);
    }
}
