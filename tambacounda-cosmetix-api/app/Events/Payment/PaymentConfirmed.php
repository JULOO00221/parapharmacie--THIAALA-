<?php

namespace App\Events\Payment;

use App\Models\Payment;

/**
 * Dispatched exactly once, inside PaymentService::markSucceeded() — only
 * on the branch that actually transitions the payment to 'paid' and
 * confirms the order, never on the idempotent early-return
 * ("if ($locked->status === 'paid') return $locked;") a duplicated
 * webhook/callback hits. Carries no business logic.
 */
class PaymentConfirmed
{
    public function __construct(public readonly Payment $payment) {}
}
