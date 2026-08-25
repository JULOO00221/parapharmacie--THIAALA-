<?php

namespace App\Http\Concerns;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Shared ownership check for anything scoped to a single order — the
 * order's authenticated owner, or a guest who proves the exact checkout
 * phone number via X-Order-Phone. Any other case is a 404 (never a 403),
 * so a guessed order_number can never confirm another customer's order
 * even exists. Used by OrderController (order detail) and
 * PaymentController (payment initiation/lookup) so this security-critical
 * rule lives in exactly one place instead of being re-implemented twice.
 */
trait AuthorizesOrderAccess
{
    /**
     * @param  array<int, string>  $with
     */
    protected function resolveOwnedOrder(Request $request, string $orderNumber, array $with = []): Order
    {
        $order = Order::where('order_number', $orderNumber)->with($with)->first();

        if ($order === null || ! $this->ownsOrder($request, $order)) {
            abort(404);
        }

        return $order;
    }

    protected function ownsOrder(Request $request, Order $order): bool
    {
        $user = $request->user('sanctum');
        if ($user !== null && $order->user_id === $user->id) {
            return true;
        }

        $providedPhone = $request->header('X-Order-Phone');
        if ($providedPhone !== null && hash_equals((string) $order->customer_phone, (string) $providedPhone)) {
            return true;
        }

        return false;
    }
}
