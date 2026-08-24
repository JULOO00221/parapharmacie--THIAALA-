<?php

namespace App\Exceptions\Order;

class InvalidPaymentMethodException extends OrderException
{
    public function __construct(public readonly string $paymentMethod)
    {
        parent::__construct("Moyen de paiement non pris en charge : {$paymentMethod}.");
    }
}
