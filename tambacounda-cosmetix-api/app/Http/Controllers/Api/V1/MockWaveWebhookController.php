<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dev/test-only tool driven by the Next.js mock checkout page
 * (/paiement/mock/[paymentId]) — NOT the real Wave webhook. No signature,
 * no raw-body parsing, no relation whatsoever to Wave-Signature: this
 * exists purely so a human can simulate "the customer completed/failed/
 * let expire their Wave payment" while WAVE_MOCK is on. The route this
 * controller is bound to is only ever registered when
 * config('services.wave.mock') is true AND the app isn't running in
 * production (see routes/api.php) — this action() re-checks the same
 * guard so the endpoint stays inert even if it were somehow reached.
 */
class MockWaveWebhookController extends Controller
{
    public function simulate(Request $request, string $transactionId, PaymentService $payments): JsonResponse
    {
        abort_unless(config('services.wave.mock') && ! app()->environment('production'), 404);

        $validated = $request->validate([
            'outcome' => ['required', 'string', Rule::in(['succeeded', 'failed', 'expired'])],
        ]);

        $payment = Payment::where('transaction_id', $transactionId)->where('provider', 'wave')->first();

        if ($payment === null) {
            abort(404);
        }

        $updated = match ($validated['outcome']) {
            'succeeded' => $payments->markSucceeded($payment),
            'failed' => $payments->markFailed($payment, 'simulated_failure'),
            'expired' => $payments->expire($payment),
        };

        return PaymentResource::make($updated)->response();
    }
}
