<?php

namespace App\Exceptions\Payment;

/**
 * Refuses initiating a Wave payment for an order checked out with
 * cash_in_store, or vice versa — the provider passed to
 * PaymentService::initiate() must match orders.payment_method.
 */
class PaymentProviderMismatchException extends PaymentException
{
    public function __construct(public readonly string $orderPaymentMethod, public readonly string $requestedProvider)
    {
        parent::__construct(
            "La commande a été créée avec le moyen de paiement \"{$orderPaymentMethod}\", incompatible avec \"{$requestedProvider}\"."
        );
    }
}
