<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Payment\PaymentNotFoundException;
use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The REAL future Wave webhook (POST /api/v1/payments/wave/callback) —
 * never confused with MockWaveWebhookController, which is a dev-only tool
 * driven by the local simulation page. This endpoint is fully wired
 * (signature verification, event dispatch to PaymentService) but cannot
 * be exercised for real until config('services.wave.webhook_secret') is
 * populated with an actual Wave-issued secret: without it, every request
 * is refused at the signature check, which is the correct behaviour —
 * this controller must never trust an unsigned or wrongly-signed payload.
 *
 * See https://docs.wave.com/webhook for the exact signature scheme.
 */
class WaveWebhookController extends Controller
{
    /** Per docs.wave.com/webhook: reject anything older than 5 minutes. */
    private const MAX_TIMESTAMP_SKEW_SECONDS = 300;

    public function handle(Request $request, PaymentService $payments): JsonResponse
    {
        // Signature verification MUST happen on the exact bytes Wave
        // signed — never json_decode() then re-encode/re-verify, which
        // would silently change key order/whitespace and break the HMAC.
        $rawBody = $request->getContent();

        if (! $this->hasValidSignature($rawBody, $request->header('Wave-Signature'))) {
            Log::warning('Wave webhook: missing or invalid signature — request refused.');
            abort(401);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload) || ! isset($payload['type'], $payload['data']['id'])) {
            abort(400, 'Malformed webhook payload.');
        }

        $externalReference = (string) $payload['data']['id'];

        try {
            match ($payload['type']) {
                'checkout.session.completed' => $payments->handleWebhook('wave', $externalReference, 'succeeded', [
                    'amount' => $payload['data']['amount'] ?? null,
                    'currency' => $payload['data']['currency'] ?? null,
                ]),
                'checkout.session.payment_failed' => $payments->handleWebhook('wave', $externalReference, 'failed', [
                    'failure_reason' => 'wave_reported_failure',
                ]),
                // Événement non pertinent pour ce projet (ex. b2b.*,
                // merchant.*, test.test_event) — acquitté sans action,
                // jamais une erreur qui déclencherait un retry Wave inutile.
                default => null,
            };
        } catch (PaymentNotFoundException $e) {
            // Référence inconnue de nous : on acquitte quand même (200)
            // pour ne pas provoquer un retry Wave sans fin sur un
            // événement qu'on ne pourra de toute façon jamais relier.
            Log::warning($e->getMessage());
        }

        return response()->json(['message' => 'ok']);
    }

    private function hasValidSignature(string $rawBody, ?string $header): bool
    {
        $secret = config('services.wave.webhook_secret');

        if (empty($secret) || $header === null) {
            return false;
        }

        if (! preg_match('/^t=(\d+),v1=([0-9a-f]+)$/', $header, $matches)) {
            return false;
        }

        [, $timestamp, $signature] = $matches;

        if (abs(time() - (int) $timestamp) > self::MAX_TIMESTAMP_SKEW_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.$rawBody, (string) $secret);

        return hash_equals($expected, $signature);
    }
}
