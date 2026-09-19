<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Tells the Next.js frontend to drop its cached catalogue reads
 * (POST /api/revalidate, which expires the `catalog` cache tag).
 *
 * Shared by the debounced RevalidateFrontendCatalog job (after catalogue
 * changes) and the catalog:revalidate-frontend command (after deploys).
 */
class FrontendCacheRevalidator
{
    public function isConfigured(): bool
    {
        return filled(config('services.frontend.url')) && filled(config('services.frontend.revalidate_secret'));
    }

    public function endpoint(): string
    {
        return rtrim((string) config('services.frontend.url'), '/').'/api/revalidate';
    }

    /**
     * @throws RequestException on a non-2xx answer
     * @throws ConnectionException when the frontend is unreachable
     */
    public function revalidate(): void
    {
        Http::withToken((string) config('services.frontend.revalidate_secret'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(10)
            ->post($this->endpoint())
            ->throw();
    }
}
