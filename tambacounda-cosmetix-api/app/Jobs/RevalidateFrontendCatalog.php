<?php

namespace App\Jobs;

use App\Services\FrontendCacheRevalidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\DebounceFor;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Empties the frontend's catalogue cache after catalogue changes.
 *
 * Debounced: every change dispatches it, but only the latest dispatch runs,
 * 10 seconds after the last change — a CSV import of 500 products or a
 * burst of orders (stock) makes one call, not hundreds. `maxWait` forces a
 * run at least every 60 seconds during a continuous stream of changes, so
 * the site never lags more than about a minute. Last-writer-wins also
 * means nothing is lost: the job that runs sees every change before it.
 *
 * After commit: a change inside a transaction only triggers the call once
 * committed; on rollback Laravel releases the debounce token, so an earlier
 * pending run still happens.
 */
#[DebounceFor(10, maxWait: 60)]
class RevalidateFrontendCatalog implements ShouldQueue, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    public function handle(FrontendCacheRevalidator $revalidator): void
    {
        if (! $revalidator->isConfigured()) {
            // Local development without the frontend: the 5-minute cache
            // expiry remains the only mechanism, which is fine there.
            Log::info('frontend.revalidate.skipped_not_configured');

            return;
        }

        // A failure throws: Laravel retries per $tries/$backoff, then the job
        // lands in failed_jobs. The frontend cache still expires within
        // 5 minutes regardless.
        $revalidator->revalidate();

        Log::info('frontend.revalidate.done', ['endpoint' => $revalidator->endpoint()]);
    }
}
