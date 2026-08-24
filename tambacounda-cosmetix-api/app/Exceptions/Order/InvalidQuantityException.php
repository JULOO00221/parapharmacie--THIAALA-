<?php

namespace App\Exceptions\Order;

class InvalidQuantityException extends OrderException
{
    public function __construct(public readonly int $productId, public readonly int $quantity, public readonly int $max)
    {
        parent::__construct("Quantité invalide ({$quantity}) pour le produit {$productId} : doit être comprise entre 1 et {$max}.");
    }
}
