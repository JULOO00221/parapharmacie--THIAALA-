<?php

namespace App\Observers;

use App\Jobs\RevalidateFrontendCatalog;

/**
 * Every write to data the storefront caches (products, categories, brands,
 * tags, images, stock) asks the frontend to drop its catalogue cache.
 * Covers every origin: Filament, services, imports, orders (stock).
 *
 * Only dispatches: the job is debounced and runs after commit, so this
 * observer costs nothing noticeable on the write path and never blocks it.
 */
class CatalogCacheObserver
{
    public function saved(): void
    {
        RevalidateFrontendCatalog::dispatch();
    }

    public function deleted(): void
    {
        RevalidateFrontendCatalog::dispatch();
    }

    public function restored(): void
    {
        RevalidateFrontendCatalog::dispatch();
    }

    public function forceDeleted(): void
    {
        RevalidateFrontendCatalog::dispatch();
    }
}
