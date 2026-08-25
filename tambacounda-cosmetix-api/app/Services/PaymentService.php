<?php

namespace App\Services;

use App\Exceptions\Payment\InvalidPaymentTransitionException;
use App\Exceptions\Payment\OrderAlreadyPaidException;
use App\Exceptions\Payment\OrderNotPayableException;
use App\Exceptions\Payment\PaymentNotFoundException;
use App\Exceptions\Payment\PaymentProviderMismatchException;
use App\Exceptions\Payment\UnsupportedPaymentProviderException;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\PaymentProviderFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Owns every Payment mutation — no controller, Filament action, or
 * webhook handler ever writes to a Payment row directly. Complements
 * OrderService, which stays the sole authority on order/stock state:
 * this service only ever asks OrderService::confirm()/cancel() to make
 * those changes, never touches quantity_reserved/quantity_available or
 * orders.status itself.
 */
class PaymentService
{
    /**
     * Only 'wave' is wired up for now — Orange Money is out of scope for
     * this iteration (see PaymentProviderFactory).
     */
    public const SUPPORTED_PROVIDERS = ['wave'];

    private const ACTIVE_STATUSES = ['pending', 'processing'];

    /** Configurable — see initiate(). 15 minutes matches the Phase 8 decision. */
    public const DEFAULT_EXPIRATION_MINUTES = 15;

    public function __construct(private readonly PaymentProviderFactory $providers) {}

    /**
     * Creates (or reuses) the active payment attempt for an order. Never
     * trusts any amount from the caller: amount/currency are pinned from
     * order.total/XOF, exactly as OrderService pins order totals from
     * live product prices rather than the checkout payload.
     */
    public function initiate(Order $order, string $provider): Payment
    {
        if (! in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            throw new UnsupportedPaymentProviderException($provider);
        }

        return DB::transaction(function () use ($order, $provider) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->payment_method !== $provider) {
                throw new PaymentProviderMismatchException((string) $lockedOrder->payment_method, $provider);
            }

            if ($lockedOrder->payment_status === 'paid') {
                throw new OrderAlreadyPaidException();
            }

            if ($lockedOrder->status !== 'pending') {
                throw new OrderNotPayableException($lockedOrder->status);
            }

            // Une seule tentative active par provider/commande : une
            // tentative pending/processing déjà ouverte est réutilisée
            // telle quelle plutôt que d'en créer une seconde en parallèle.
            // Le passage à 'expired' d'une tentative dont expires_at est
            // dépassée reste exclusivement le travail du job d'expiration
            // (voir expire()) — jamais improvisé ici.
            $existing = Payment::where('order_id', $lockedOrder->id)
                ->where('provider', $provider)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $payment = Payment::create([
                'order_id' => $lockedOrder->id,
                'provider' => $provider,
                'transaction_id' => $this->generateTransactionId(),
                'amount' => $lockedOrder->total,
                'currency' => 'XOF',
                'status' => 'pending',
            ]);

            $result = $this->providers->for($provider)->initiate($payment);

            $payment->update([
                'status' => 'processing',
                'external_reference' => $result->externalReference,
                'metadata' => $result->raw,
                'expires_at' => $result->expiresAt ?? now()->addMinutes(self::DEFAULT_EXPIRATION_MINUTES),
            ]);

            return $payment->refresh();
        });
    }

    /**
     * Entry point for a REAL provider webhook (not the mock simulator,
     * which already holds the Payment and calls markSucceeded/markFailed
     * directly). Resolves the Payment from the provider's own reference
     * — never trusts an order/payment id supplied by the caller — then
     * dispatches to the same idempotent primitives.
     */
    public function handleWebhook(string $provider, string $externalReference, string $status, array $context = []): Payment
    {
        return DB::transaction(function () use ($provider, $externalReference, $status, $context) {
            $payment = Payment::where('provider', $provider)
                ->where('external_reference', $externalReference)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                throw PaymentNotFoundException::forExternalReference($externalReference);
            }

            return match ($status) {
                'succeeded', 'paid' => $this->markSucceeded(
                    $payment,
                    confirmedAmount: $context['amount'] ?? null,
                    confirmedCurrency: $context['currency'] ?? null,
                ),
                'failed' => $this->markFailed($payment, $context['failure_reason'] ?? 'provider_reported_failure'),
                default => throw new \InvalidArgumentException("Unknown webhook status: {$status}"),
            };
        });
    }

    /**
     * Idempotent: already-paid is a silent no-op (same pattern as
     * OrderService::markAsPaid). When $confirmedAmount/$confirmedCurrency
     * are given (real webhook path), they're checked against the amount
     * pinned at initiate() time — a mismatch never marks the payment
     * paid, it's redirected to markFailed() instead. The mock path
     * passes neither (there's no external confirmation to mismatch
     * against — it's our own simulation).
     */
    public function markSucceeded(Payment $payment, ?string $confirmedAmount = null, ?string $confirmedCurrency = null): Payment
    {
        return DB::transaction(function () use ($payment, $confirmedAmount, $confirmedCurrency) {
            $locked = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'paid') {
                return $locked;
            }

            if (! in_array($locked->status, self::ACTIVE_STATUSES, true)) {
                throw new InvalidPaymentTransitionException($locked->status, 'paid');
            }

            if ($confirmedCurrency !== null && $confirmedCurrency !== $locked->currency) {
                Log::warning('Payment currency mismatch — refused.', [
                    'transaction_id' => $locked->transaction_id,
                    'expected' => $locked->currency,
                    'confirmed' => $confirmedCurrency,
                ]);

                return $this->markFailed($locked, 'currency_mismatch');
            }

            if ($confirmedAmount !== null && bccomp($confirmedAmount, (string) $locked->amount, 2) !== 0) {
                Log::warning('Payment amount mismatch — refused.', [
                    'transaction_id' => $locked->transaction_id,
                    'expected' => (string) $locked->amount,
                    'confirmed' => $confirmedAmount,
                ]);

                return $this->markFailed($locked, 'amount_mismatch');
            }

            $order = Order::where('id', $locked->order_id)->lockForUpdate()->firstOrFail();

            if ($order->status === 'cancelled') {
                // Paiement reçu après expiration/annulation : jamais de
                // réactivation automatique — refusé, journalisé pour
                // traitement manuel (voir le rapport d'audit Phase 8, §5.8).
                Log::warning('Payment succeeded for an already-cancelled order — refused, needs manual review.', [
                    'transaction_id' => $locked->transaction_id,
                    'order_number' => $order->order_number,
                ]);

                return $this->markFailed($locked, 'order_already_cancelled');
            }

            $locked->update(['status' => 'paid', 'paid_at' => now()]);

            // markAsPaid() est la même méthode que le flux cash utilise et
            // reste idempotente/protégée contre une commande annulée —
            // aucune logique de paiement dupliquée ici. confirm() reste le
            // seul point d'entrée pour la transition de statut + le stock.
            $orderService = app(OrderService::class);
            $orderService->markAsPaid($order);
            $orderService->confirm($order->fresh());

            return $locked->refresh();
        });
    }

    /** Idempotent: already-failed is a silent no-op. Order/stock are left untouched — the customer can retry. */
    public function markFailed(Payment $payment, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $reason) {
            $locked = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'failed') {
                return $locked;
            }

            if (! in_array($locked->status, self::ACTIVE_STATUSES, true)) {
                throw new InvalidPaymentTransitionException($locked->status, 'failed');
            }

            $locked->update(['status' => 'failed', 'failure_reason' => $reason]);

            return $locked->refresh();
        });
    }

    /**
     * Idempotent. Only cancels the order when it's still pending — a
     * payment attempt expiring must never cancel an order a DIFFERENT,
     * already-succeeded attempt has already confirmed.
     */
    public function expire(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $locked = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, self::ACTIVE_STATUSES, true)) {
                return $locked;
            }

            $locked->update(['status' => 'expired']);

            $order = Order::where('id', $locked->order_id)->lockForUpdate()->firstOrFail();

            if ($order->status === 'pending') {
                app(OrderService::class)->cancel($order);
            }

            return $locked->refresh();
        });
    }

    /** Idempotent. Mock provider's refund() is a pure no-op — never a real transaction. */
    public function refund(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $locked = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'refunded') {
                return $locked;
            }

            if ($locked->status !== 'paid') {
                throw new InvalidPaymentTransitionException($locked->status, 'refunded');
            }

            $this->providers->for($locked->provider)->refund($locked);

            $locked->update(['status' => 'refunded']);

            return $locked->refresh();
        });
    }

    /**
     * Opaque, non-sequential identifier — same policy as
     * Order::generateOrderNumber(): never the auto-incrementing id,
     * collision checked before use.
     */
    private function generateTransactionId(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = sprintf('PAY-%s-%s', now()->format('Ymd'), Str::upper(Str::random(10)));

            if (! Payment::where('transaction_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Impossible de générer un transaction_id de paiement unique après plusieurs tentatives.');
    }
}
