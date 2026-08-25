<?php

namespace App\Jobs;

use App\WhatsApp\WhatsAppPhoneMasker;
use App\WhatsApp\WhatsAppProviderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The only place that actually invokes a WhatsAppProviderInterface.
 * Implements ShouldQueueAfterCommit — OrderService/PaymentService dispatch
 * the events that lead here from inside DB::transaction() blocks; without
 * this, a fast queue worker could pick up the job before the transaction
 * that created the Order/Payment/Stock row even commits. A WhatsApp
 * failure here (thrown exception → Laravel retries per $tries/$backoff,
 * eventually lands in failed_jobs) can never affect the order/payment
 * that triggered it — this job never touches Order/Payment/Stock at all,
 * only reads what it was constructed with.
 */
class SendWhatsAppNotification implements ShouldQueue, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 120];

    /**
     * @param  array<string, string>  $parameters
     */
    public function __construct(
        public readonly string $phone,
        public readonly string $template,
        public readonly array $parameters,
        public readonly string $dedupeKey,
    ) {}

    public function handle(WhatsAppProviderFactory $providers): void
    {
        $cacheKey = "whatsapp:sent:{$this->dedupeKey}";

        // Idempotence appliquée UNIQUEMENT après un succès confirmé (voir
        // en bas de cette méthode) — jamais avant l'appel provider, sinon
        // un échec réel neutraliserait silencieusement $tries/$backoff.
        if (Cache::has($cacheKey)) {
            Log::info('whatsapp.notification.skipped_duplicate', [
                'template' => $this->template,
                'phone' => WhatsAppPhoneMasker::mask($this->phone),
            ]);

            return;
        }

        $result = $providers->for()->sendTemplate($this->phone, $this->template, $this->parameters);

        if (! $result->success) {
            Log::error('whatsapp.notification.failed', [
                'provider' => config('services.whatsapp.provider'),
                'phone' => WhatsAppPhoneMasker::mask($this->phone),
                'template' => $this->template,
                'status' => $result->status,
                'error' => $result->error,
            ]);

            // Lève pour déclencher le retry Laravel ($tries/$backoff) —
            // un handle() qui se termine sans exception est considéré
            // réussi par Laravel et ne serait jamais retenté.
            throw new RuntimeException("Échec d'envoi WhatsApp ({$this->template}) : ".($result->error ?? 'raison inconnue'));
        }

        Cache::put($cacheKey, true, now()->addDays(30));

        Log::info('whatsapp.notification.sent', [
            'provider' => config('services.whatsapp.provider'),
            'phone' => WhatsAppPhoneMasker::mask($this->phone),
            'template' => $this->template,
            'status' => $result->status,
            'external_id' => $result->externalId,
        ]);
    }
}
