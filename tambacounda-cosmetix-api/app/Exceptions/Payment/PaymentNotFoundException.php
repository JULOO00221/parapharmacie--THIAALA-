<?php

namespace App\Exceptions\Payment;

class PaymentNotFoundException extends PaymentException
{
    public static function forExternalReference(string $externalReference): self
    {
        return new self("Aucun paiement ne correspond à la référence {$externalReference}.");
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
