<?php

namespace App\Payments;

use App\Exceptions\Payment\UnsupportedPaymentProviderException;

/**
 * Sole place that decides which concrete PaymentProviderInterface handles
 * a given provider string — PaymentService, the controllers and Filament
 * never branch on "if provider === 'wave'" themselves. Swapping the mock
 * for a real Wave integration later is a one-line change here (plus
 * writing WavePaymentProvider); nothing else in the payment stack moves.
 */
class PaymentProviderFactory
{
    public function for(string $provider): PaymentProviderInterface
    {
        return match ($provider) {
            // config('services.wave.mock') defaults to true — a real
            // WavePaymentProvider is not written yet (no Wave Developer
            // access), so this arm has no non-mock branch today.
            'wave' => app(MockWavePaymentProvider::class),
            default => throw new UnsupportedPaymentProviderException($provider),
        };
    }
}
