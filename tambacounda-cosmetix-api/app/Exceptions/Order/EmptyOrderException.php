<?php

namespace App\Exceptions\Order;

class EmptyOrderException extends OrderException
{
    public function __construct()
    {
        parent::__construct('Une commande doit contenir au moins un produit.');
    }
}
