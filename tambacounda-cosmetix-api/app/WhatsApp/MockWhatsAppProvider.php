<?php

namespace App\WhatsApp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Simulates sending a WhatsApp template message without ever making a
 * network call — no request to any WhatsApp/Meta endpoint, no real API
 * token used. Always "succeeds" (mirrors MockWavePaymentProvider's own
 * happy-path-only design — failure scenarios for tests are simulated by
 * substituting a different WhatsAppProviderInterface binding, never by
 * this class faking an error).
 *
 * Every simulated send is logged with a structured, verifiable log line
 * (never a real token, never an unmasked phone number) so a test can
 * assert against it, and SendWhatsAppNotification::handle() logs its own
 * outcome on top of this — this class's own log line is a record of "the
 * mock was actually invoked", not the final audit record.
 */
class MockWhatsAppProvider implements WhatsAppProviderInterface
{
    public function sendTemplate(string $phone, string $template, array $parameters): WhatsAppMessageResult
    {
        $externalId = 'mock-wa-'.Str::lower(Str::random(16));

        Log::info('whatsapp.mock.message_simulated', [
            'phone' => WhatsAppPhoneMasker::mask($phone),
            'template' => $template,
            'parameters' => $parameters,
            'external_id' => $externalId,
        ]);

        return new WhatsAppMessageResult(
            success: true,
            status: 'sent',
            externalId: $externalId,
        );
    }
}
