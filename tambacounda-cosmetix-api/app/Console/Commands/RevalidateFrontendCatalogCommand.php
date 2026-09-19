<?php

namespace App\Console\Commands;

use App\Services\FrontendCacheRevalidator;
use Illuminate\Console\Command;
use Throwable;

/**
 * Empties the frontend's catalogue cache right away, synchronously — meant
 * for the deploy script, after migrations: a deploy can change what the API
 * returns (new fields, new counts) without any model being saved.
 */
class RevalidateFrontendCatalogCommand extends Command
{
    protected $signature = 'catalog:revalidate-frontend';

    protected $description = 'Vide immédiatement le cache du catalogue du frontend Next.js (à lancer après chaque déploiement).';

    public function handle(FrontendCacheRevalidator $revalidator): int
    {
        if (! $revalidator->isConfigured()) {
            $this->error('FRONTEND_URL ou FRONTEND_REVALIDATE_SECRET manquant : cache du frontend non vidé.');

            return self::FAILURE;
        }

        try {
            $revalidator->revalidate();
        } catch (Throwable $exception) {
            $this->error("Échec de l'appel à {$revalidator->endpoint()} : {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Cache du catalogue vidé ({$revalidator->endpoint()}).");

        return self::SUCCESS;
    }
}
