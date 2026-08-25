<?php

namespace App\Payments;

use Illuminate\Support\Carbon;

/**
 * What a provider hands back right after creating a checkout attempt.
 * Field names deliberately mirror what PaymentService needs to persist on
 * the Payment row — never the provider's raw response shape directly,
 * so a future real WavePaymentProvider only needs to map Wave's actual
 * JSON onto this same DTO for PaymentService to work unchanged.
 */
final readonly class PaymentInitiationResult
{
    /**
     * @param  array<string, mixed>  $raw  Full provider response, stored as-is in payments.metadata — functional data only, never a secret.
     */
    public function __construct(
        public ?string $externalReference,
        public ?string $checkoutUrl,
        public ?Carbon $expiresAt,
        public array $raw,
    ) {}
}
