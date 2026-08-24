<?php

namespace App\Exceptions\Order;

class ProductNotActiveException extends OrderException
{
    public function __construct(public readonly int $productId)
    {
        parent::__construct("Produit indisponible à la vente (id {$productId}).");
    }
}
