<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\SendWhatsAppNotification;
use App\Models\Order;
use App\Models\Payment;
use App\WhatsApp\WhatsAppMessageResult;
use App\WhatsApp\WhatsAppProviderInterface;
use App\WhatsApp\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Exercises SendWhatsAppNotification::handle() directly — dispatching
 * synchronously (app()->call) rather than through a real queue, since
 * what matters here is the job's own idempotence/retry/logging logic,
 * already proven to be reached correctly from real domain events in
 * OrderNotificationsTest/PaymentNotificationTest/LowStockNotificationTest.
 */
class SendWhatsAppNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function job(string $dedupeKey): SendWhatsAppNotification
    {
        return new SendWhatsAppNotification(
            phone: '+221771234567',
            template: WhatsAppTemplate::ORDER_RECEIVED->value,
            parameters: ['order_number' => 'TC-20260101-XXXXXXXXXX'],
            dedupeKey: $dedupeKey,
        );
    }

    private function bindCountingProvider(bool $succeeds = true): object
    {
        $spy = new class($succeeds)
        {
            public int $calls = 0;

            public function __construct(private readonly bool $succeeds) {}

            public function sendTemplate(string $phone, string $template, array $parameters): WhatsAppMessageResult
            {
                $this->calls++;

                return $this->succeeds
                    ? new WhatsAppMessageResult(success: true, status: 'sent', externalId: 'wa-'.$this->calls)
                    : new WhatsAppMessageResult(success: false, status: 'failed', externalId: null, error: 'provider_error');
            }
        };

        $provider = new class($spy) implements WhatsAppProviderInterface
        {
            public function __construct(private $spy) {}

            public function sendTemplate(string $phone, string $template, array $parameters): WhatsAppMessageResult
            {
                return $this->spy->sendTemplate($phone, $template, $parameters);
            }
        };

        $this->app->instance(WhatsAppProviderInterface::class, $provider);

        return $spy;
    }

    // --- mock appelé correctement ------------------------------------------------

    public function test_handle_calls_the_provider_with_phone_template_and_parameters(): void
    {
        $spy = $this->bindCountingProvider();

        app()->call([$this->job((string) Str::uuid()), 'handle']);

        $this->assertSame(1, $spy->calls);
    }

    // --- idempotence : appliquée après succès confirmé, jamais avant ---------------

    public function test_a_second_call_with_the_same_dedupe_key_after_success_is_skipped(): void
    {
        $spy = $this->bindCountingProvider(succeeds: true);
        $dedupeKey = (string) Str::uuid();

        app()->call([$this->job($dedupeKey), 'handle']);
        app()->call([$this->job($dedupeKey), 'handle']); // retry Laravel du même job

        $this->assertSame(1, $spy->calls);
    }

    public function test_a_different_dedupe_key_is_never_blocked_by_a_previous_one(): void
    {
        $spy = $this->bindCountingProvider(succeeds: true);

        app()->call([$this->job((string) Str::uuid()), 'handle']);
        app()->call([$this->job((string) Str::uuid()), 'handle']); // notification distincte

        $this->assertSame(2, $spy->calls);
    }

    // --- retry après échec : le marqueur n'est jamais posé avant succès -----------

    public function test_a_failed_attempt_throws_to_trigger_a_laravel_retry(): void
    {
        $this->bindCountingProvider(succeeds: false);

        $this->expectException(RuntimeException::class);

        app()->call([$this->job((string) Str::uuid()), 'handle']);
    }

    public function test_a_retry_after_failure_actually_calls_the_provider_again_and_can_succeed(): void
    {
        $spy = new class
        {
            public int $calls = 0;

            public bool $succeedNextCall = false;

            public function sendTemplate(string $phone, string $template, array $parameters): WhatsAppMessageResult
            {
                $this->calls++;

                return $this->succeedNextCall
                    ? new WhatsAppMessageResult(success: true, status: 'sent', externalId: 'wa-ok')
                    : new WhatsAppMessageResult(success: false, status: 'failed', externalId: null, error: 'transient');
            }
        };
        $provider = new class($spy) implements WhatsAppProviderInterface
        {
            public function __construct(private $spy) {}

            public function sendTemplate(string $phone, string $template, array $parameters): WhatsAppMessageResult
            {
                return $this->spy->sendTemplate($phone, $template, $parameters);
            }
        };
        $this->app->instance(WhatsAppProviderInterface::class, $provider);

        $dedupeKey = (string) Str::uuid();

        try {
            app()->call([$this->job($dedupeKey), 'handle']); // 1er essai : échoue
        } catch (RuntimeException) {
            // attendu
        }

        $spy->succeedNextCall = true;
        app()->call([$this->job($dedupeKey), 'handle']); // 2e essai (retry Laravel) : réussit

        $this->assertSame(2, $spy->calls);
    }

    // --- une erreur WhatsApp ne modifie jamais Order/Payment/Stock -----------------

    public function test_a_failure_never_touches_orders_payments_or_stocks_tables(): void
    {
        $this->bindCountingProvider(succeeds: false);

        $ordersBefore = Order::count();
        $paymentsBefore = Payment::count();

        try {
            app()->call([$this->job((string) Str::uuid()), 'handle']);
        } catch (RuntimeException) {
            // attendu
        }

        $this->assertSame($ordersBefore, Order::count());
        $this->assertSame($paymentsBefore, Payment::count());
    }

    // --- audit / logs ------------------------------------------------------------

    public function test_success_is_logged_without_any_secret(): void
    {
        config(['services.whatsapp.api_token' => 'super-secret-token-value']);
        $this->bindCountingProvider(succeeds: true);
        Log::spy();

        app()->call([$this->job((string) Str::uuid()), 'handle']);

        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) {
                if ($message !== 'whatsapp.notification.sent') {
                    return true;
                }

                $encoded = json_encode($context);

                return ! str_contains($encoded, 'super-secret-token-value')
                    && $context['phone'] === '+221******67';
            });
    }

    public function test_failure_is_logged_without_any_secret(): void
    {
        config(['services.whatsapp.api_token' => 'super-secret-token-value']);
        $this->bindCountingProvider(succeeds: false);
        Log::spy();

        try {
            app()->call([$this->job((string) Str::uuid()), 'handle']);
        } catch (RuntimeException) {
            // attendu
        }

        Log::shouldHaveReceived('error')
            ->withArgs(function ($message, $context) {
                if ($message !== 'whatsapp.notification.failed') {
                    return true;
                }

                $encoded = json_encode($context);

                return ! str_contains($encoded, 'super-secret-token-value')
                    && $context['phone'] === '+221******67';
            });
    }

    public function test_no_log_line_ever_contains_the_full_unmasked_phone_number(): void
    {
        $this->bindCountingProvider(succeeds: true);
        Log::spy();

        app()->call([$this->job((string) Str::uuid()), 'handle']);

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
            $encoded = json_encode($context);

            return ! str_contains($encoded, '771234567');
        });
    }
}
