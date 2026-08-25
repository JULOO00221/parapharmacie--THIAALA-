<?php

namespace Tests\Feature\WhatsApp;

use App\WhatsApp\MockWhatsAppProvider;
use App\WhatsApp\WhatsAppProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MockWhatsAppProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // La garantie centrale du mock : si jamais il tentait un vrai
        // appel réseau, ce test échouerait immédiatement.
        Http::preventStrayRequests();
    }

    public function test_it_never_makes_a_network_call_and_always_succeeds(): void
    {
        $provider = new MockWhatsAppProvider();

        $result = $provider->sendTemplate('+221771234567', 'order_received', ['order_number' => 'TC-1']);

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertNotNull($result->externalId);
    }

    public function test_it_logs_the_simulated_message_with_a_masked_phone(): void
    {
        Log::spy();

        (new MockWhatsAppProvider())->sendTemplate('+221771234567', 'order_received', ['order_number' => 'TC-1']);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message, $context) => $message === 'whatsapp.mock.message_simulated'
                && $context['phone'] === '+221******67'
                && ! str_contains($context['phone'], '771234'))
            ->once();
    }

    public function test_the_factory_resolves_the_mock_provider_when_mock_is_true(): void
    {
        config(['services.whatsapp.mock' => true, 'services.whatsapp.provider' => 'mock']);

        $provider = app(WhatsAppProviderFactory::class)->for();

        $this->assertInstanceOf(MockWhatsAppProvider::class, $provider);
    }

    public function test_the_factory_forces_the_mock_provider_even_if_provider_is_misconfigured(): void
    {
        // mock=true reste le garde-fou prioritaire (décision explicite) —
        // même une valeur "provider" incohérente ne doit jamais faire
        // sortir du mode simulé tant que mock=true.
        config(['services.whatsapp.mock' => true, 'services.whatsapp.provider' => 'cloud_api']);

        $provider = app(WhatsAppProviderFactory::class)->for();

        $this->assertInstanceOf(MockWhatsAppProvider::class, $provider);
    }

    public function test_the_factory_rejects_an_unsupported_provider_when_mock_is_disabled(): void
    {
        config(['services.whatsapp.mock' => false, 'services.whatsapp.provider' => 'cloud_api']);

        $this->expectException(\RuntimeException::class);

        app(WhatsAppProviderFactory::class)->for();
    }
}
