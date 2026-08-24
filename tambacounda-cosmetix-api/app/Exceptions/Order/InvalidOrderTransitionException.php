<?php

namespace App\Exceptions\Order;

class InvalidOrderTransitionException extends OrderException
{
    public function __construct(public readonly string $from, public readonly string $to)
    {
        parent::__construct("Transition de statut impossible : {$from} → {$to}.");
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
