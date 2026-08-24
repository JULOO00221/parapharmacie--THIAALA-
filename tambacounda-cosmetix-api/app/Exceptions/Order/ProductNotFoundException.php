<?php

namespace App\Exceptions\Order;

class ProductNotFoundException extends OrderException
{
    public function __construct(public readonly int $productId)
    {
        parent::__construct("Produit introuvable (id {$productId}).");
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
