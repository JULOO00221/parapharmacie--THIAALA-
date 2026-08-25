<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Scheduled every minute (see routes/console.php) — the only automatic
 * trigger for PaymentService::expire(). Without this command, a Wave
 * payment attempt abandoned by a customer would stay pending/processing
 * forever, with its order stuck pending and its stock reserved forever.
 *
 * Idempotence/concurrency are entirely delegated to
 * PaymentService::expire() itself (lockForUpdate + a no-op on any
 * non-active status) — this command never mutates a Payment directly,
 * so running it twice concurrently, or re-selecting a row another
 * process already expired, is always safe.
 */
class ExpireStalePayments extends Command
{
    protected $signature = 'payments:expire-stale';

    protected $description = 'Expire Wave payment attempts whose expires_at has passed, releasing any reserved stock.';

    public function handle(PaymentService $payments): int
    {
        $expiredCount = 0;
        $failedCount = 0;

        Payment::query()
            ->whereIn('status', ['pending', 'processing'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($batch) use ($payments, &$expiredCount, &$failedCount) {
                foreach ($batch as $payment) {
                    try {
                        // Toujours idempotent — sûr même si un autre
                        // processus a déjà traité ce Payment entre la
                        // sélection ci-dessus et cet appel.
                        $updated = $payments->expire($payment);

                        if ($updated->status === 'expired') {
                            $expiredCount++;
                        }
                    } catch (Throwable $e) {
                        // Une tentative isolée en échec ne doit jamais
                        // interrompre le traitement des autres.
                        $failedCount++;

                        Log::error('payments:expire-stale: échec sur une tentative de paiement.', [
                            'transaction_id' => $payment->transaction_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Paiements expirés : {$expiredCount}. Échecs : {$failedCount}.");

        return self::SUCCESS;
    }
}
