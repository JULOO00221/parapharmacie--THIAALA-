<?php

namespace App\Exceptions\Order;

use RuntimeException;

/**
 * Base type for every order-creation/transition failure. Lets a future API
 * layer catch this single type to return a generic 422/409, while still
 * being able to catch a specific subclass when a distinct response (404 vs
 * 422 vs 409) is needed.
 */
abstract class OrderException extends RuntimeException
{
    /**
     * HTTP status the API layer should map this exception to. 422 by
     * default (the submitted order cannot be processed as-is); specific
     * subclasses override this for 404 (truly missing resource) or 409
     * (state conflict, e.g. insufficient stock).
     */
    public function httpStatus(): int
    {
        return 422;
    }
}
