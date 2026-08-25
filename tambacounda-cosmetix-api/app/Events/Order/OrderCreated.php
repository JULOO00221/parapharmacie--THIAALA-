<?php

namespace App\Events\Order;

use App\Models\Order;

/**
 * Dispatched exactly once, inside OrderService::buildOrder() — never on
 * the idempotent-replay path (a repeated Idempotency-Key returns the
 * existing order without ever reaching buildOrder()), so this can never
 * fire twice for the same logical checkout. Carries no business logic —
 * a pure fact, listeners decide what to do with it.
 */
class OrderCreated
{
    public function __construct(public readonly Order $order) {}
}
