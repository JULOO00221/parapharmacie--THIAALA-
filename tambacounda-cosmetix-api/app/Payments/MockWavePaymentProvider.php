<?php

namespace App\Payments;

use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Simulates the Wave Checkout API (https://docs.wave.com/checkout)
 * without ever making a network call — no api.wave.com request, no real
 * API key. The shape of initiate()'s raw response mirrors Wave's actual
 * POST /v1/checkout/sessions response (id, transaction_id,
 * wave_launch_url, checkout_status, payment_status, when_created,
 * when_expires) so PaymentService and PaymentResource work unchanged the
 * day a real WavePaymentProvider replaces this class.
 *
 * wave_launch_url points at the Next.js mock checkout page
 * (/paiement/mock/[paymentId]) instead of pay.wave.com — that page is the
 * one place a human "completes" or "fails" the simulated payment, which
 * then calls the dev-only simulate endpoint (never the real
 * /payments/wave/callback webhook).
 */
class MockWavePaymentProvider implements PaymentProviderInterface
{
    private const DEFAULT_SESSION_LIFETIME_MINUTES = 30; // même valeur par défaut que le vrai Wave Checkout

    public function initiate(Payment $payment): PaymentInitiationResult
    {
        $sessionId = 'cos-mock-'.Str::lower(Str::random(13));
        $waveTransactionId = 'MOCK'.Str::upper(Str::random(11));
        $now = Carbon::now();
        $expiresAt = $now->clone()->addMinutes(self::DEFAULT_SESSION_LIFETIME_MINUTES);

        $raw = [
            'id' => $sessionId,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => $payment->currency,
            'payment_status' => 'processing',
            'checkout_status' => 'open',
            'transaction_id' => $waveTransactionId,
            // ?order=... permet à la page mock de rediriger vers
            // /paiement/succes ou /paiement/echec sans appel API
            // supplémentaire — voir components/payment/MockWaveCheckout.tsx.
            'wave_launch_url' => rtrim((string) config('services.wave.frontend_url'), '/')
                ."/paiement/mock/{$payment->transaction_id}?order=".urlencode($payment->order->order_number),
            'when_created' => $now->utc()->format('Y-m-d\TH:i:s\Z'),
            'when_expires' => $expiresAt->utc()->format('Y-m-d\TH:i:s\Z'),
        ];

        return new PaymentInitiationResult(
            externalReference: $sessionId,
            checkoutUrl: $raw['wave_launch_url'],
            expiresAt: $expiresAt,
            raw: $raw,
        );
    }

    /**
     * Not called by the current mock flow (the simulate endpoint drives
     * PaymentService directly) — implemented for interface completeness
     * and for a future "refresh status" action, reading purely from what
     * we already stored, never a real HTTP call.
     */
    public function getStatus(Payment $payment): PaymentStatusResult
    {
        return new PaymentStatusResult(
            status: (string) $payment->status,
            confirmedAmount: (string) $payment->amount,
            confirmedCurrency: (string) $payment->currency,
        );
    }

    /** No real session to expire anywhere — PaymentService owns the local state transition. */
    public function expire(Payment $payment): void {}

    /** No real transaction to refund anywhere — PaymentService owns the local state transition. */
    public function refund(Payment $payment): void {}
}
