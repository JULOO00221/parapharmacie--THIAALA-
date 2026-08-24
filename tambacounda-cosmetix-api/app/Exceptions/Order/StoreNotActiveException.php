<?php

namespace App\Exceptions\Order;

class StoreNotActiveException extends OrderException
{
    public function __construct(public readonly int $storeId)
    {
        parent::__construct("Boutique inactive (id {$storeId}).");
    }
}
