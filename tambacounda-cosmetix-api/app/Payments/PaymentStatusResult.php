<?php

namespace App\Payments;

/**
 * Result of a server-to-server status check (PaymentProviderInterface::getStatus)
 * — used when we need to actively re-verify a payment with the provider
 * rather than wait for its webhook (e.g. a future "check status" retry
 * job, or a user-triggered refresh on the payment result page).
 */
final readonly class PaymentStatusResult
{
    public function __construct(
        public string $status,
        public ?string $confirmedAmount,
        public ?string $confirmedCurrency,
    ) {}
}
