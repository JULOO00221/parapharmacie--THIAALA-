<?php

namespace App\Exceptions\Payment;

class InvalidPaymentTransitionException extends PaymentException
{
    public function __construct(public readonly string $from, public readonly string $to)
    {
        parent::__construct("Transition de statut de paiement impossible : {$from} → {$to}.");
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
