<?php

namespace App\Events\Order;

use App\Models\Order;

/**
 * Dispatched exactly once per successful transition, inside
 * OrderService::transition() — the single choke point every public
 * transition method (confirm/preparing/ready/delivered/cancel) already
 * goes through, protected by the same lockForUpdate() + allowed-transition
 * check that guards the transition itself. A rejected transition
 * (InvalidOrderTransitionException) never reaches the dispatch line.
 * Carries no business logic — listeners decide what $to means.
 */
class OrderStatusChanged
{
    public function __construct(
        public readonly Order $order,
        public readonly string $from,
        public readonly string $to,
    ) {}
}
