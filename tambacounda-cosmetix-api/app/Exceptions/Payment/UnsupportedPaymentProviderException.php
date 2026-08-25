<?php

namespace App\Exceptions\Payment;

class UnsupportedPaymentProviderException extends PaymentException
{
    public function __construct(public readonly string $provider)
    {
        parent::__construct("Fournisseur de paiement non pris en charge : {$provider}.");
    }
}
