<?php

namespace App\Payments;

use App\Models\Payment;

/**
 * Every real or mock payment provider implements this — PaymentService
 * never knows which one it's talking to. Swapping the mock for real Wave
 * later means writing a WavePaymentProvider implementing this same
 * interface and pointing PaymentProviderFactory at it; nothing in
 * PaymentService, the controllers, or the Filament resource changes.
 */
interface PaymentProviderInterface
{
    public function initiate(Payment $payment): PaymentInitiationResult;

    public function getStatus(Payment $payment): PaymentStatusResult;

    public function expire(Payment $payment): void;

    public function refund(Payment $payment): void;
}
