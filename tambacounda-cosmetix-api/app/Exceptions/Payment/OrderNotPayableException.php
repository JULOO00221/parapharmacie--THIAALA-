<?php

namespace App\Exceptions\Payment;

class OrderNotPayableException extends PaymentException
{
    public function __construct(public readonly string $orderStatus)
    {
        parent::__construct("Cette commande ne peut plus être payée (statut actuel : {$orderStatus}).");
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
