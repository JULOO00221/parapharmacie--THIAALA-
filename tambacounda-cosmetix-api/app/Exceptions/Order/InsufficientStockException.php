<?php

namespace App\Exceptions\Order;

class InsufficientStockException extends OrderException
{
    public function __construct(public readonly int $productId, public readonly int $requested, public readonly int $available)
    {
        parent::__construct("Stock insuffisant pour le produit {$productId} : {$requested} demandé(s), {$available} disponible(s).");
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
