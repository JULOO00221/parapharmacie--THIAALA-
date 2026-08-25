<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\AuthorizesOrderAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InitiatePaymentRequest;
use App\Http\Resources\V1\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Same access policy as OrderController::show — a guest proves ownership
 * via X-Order-Phone, an authenticated customer via their own order_id.
 * No auth:sanctum middleware on either route: a guest checkout must be
 * able to initiate and check its own Wave payment exactly like it can
 * already view its own order.
 */
class PaymentController extends Controller
{
    use AuthorizesOrderAccess;

    public function store(InitiatePaymentRequest $request, string $orderNumber, PaymentService $payments): JsonResponse
    {
        $order = $this->resolveOwnedOrder($request, $orderNumber);

        $payment = $payments->initiate($order, $request->validated('provider'));

        return PaymentResource::make($payment)->response()->setStatusCode(201);
    }

    public function show(Request $request, string $orderNumber, string $transactionId): PaymentResource
    {
        $order = $this->resolveOwnedOrder($request, $orderNumber);

        $payment = Payment::where('order_id', $order->id)
            ->where('transaction_id', $transactionId)
            ->first();

        if ($payment === null) {
            abort(404);
        }

        return PaymentResource::make($payment);
    }
}
