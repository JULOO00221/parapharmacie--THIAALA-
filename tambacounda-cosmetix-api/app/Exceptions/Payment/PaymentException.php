<?php

namespace App\Exceptions\Payment;

use RuntimeException;

/**
 * Base type for every payment-initiation/transition failure — same
 * pattern as App\Exceptions\Order\OrderException, kept as a separate
 * hierarchy since payment failures are a distinct concern from order
 * failures even though both are rendered the same way in bootstrap/app.php.
 */
abstract class PaymentException extends RuntimeException
{
    public function httpStatus(): int
    {
        return 422;
    }
}
