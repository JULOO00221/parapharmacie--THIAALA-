<?php

namespace App\Exceptions\Order;

class StoreNotFoundException extends OrderException
{
    public function __construct(public readonly int $storeId)
    {
        parent::__construct("Boutique introuvable (id {$storeId}).");
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
