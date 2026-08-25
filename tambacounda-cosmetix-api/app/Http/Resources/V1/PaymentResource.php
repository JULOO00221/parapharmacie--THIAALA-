<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public payment payload. Deliberately never includes the internal
 * auto-incrementing id, order_id, external_reference (the provider's own
 * session/transaction reference) or the raw metadata blob — none of that
 * is needed by the frontend and metadata in particular is meant for
 * Filament/support diagnostics only. checkout_url is derived from
 * metadata rather than stored as its own column, and only surfaced while
 * the payment is still open — a paid/failed/expired attempt has no
 * checkout left to redirect to.
 *
 * @mixin \App\Models\Payment
 */
class PaymentResource extends JsonResource
{
    private const OPEN_STATUSES = ['pending', 'processing'];

    public function toArray(Request $request): array
    {
        return [
            'transaction_id' => $this->transaction_id,
            'provider' => $this->provider,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'checkout_url' => in_array($this->status, self::OPEN_STATUSES, true)
                ? ($this->metadata['wave_launch_url'] ?? null)
                : null,
            'failure_reason' => $this->failure_reason,
            'expires_at' => $this->expires_at,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
        ];
    }
}
