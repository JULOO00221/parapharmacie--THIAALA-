<?php

namespace App\Exceptions\Order;

class InvalidDeliveryZoneException extends OrderException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function required(): self
    {
        return new self('Une zone de livraison est obligatoire lorsque la commande n\'est pas un retrait en boutique.');
    }

    public static function notFound(int $zoneId): self
    {
        return new self("Zone de livraison introuvable (id {$zoneId}).");
    }

    public static function notActive(int $zoneId): self
    {
        return new self("Zone de livraison inactive (id {$zoneId}).");
    }

    public static function addressRequired(): self
    {
        return new self('Une adresse de livraison est obligatoire lorsque la commande n\'est pas un retrait en boutique.');
    }
}
