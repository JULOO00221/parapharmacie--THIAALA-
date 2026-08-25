<?php

namespace App\Exceptions\Payment;

class OrderAlreadyPaidException extends PaymentException
{
    public function __construct()
    {
        parent::__construct('Cette commande est déjà payée.');
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
