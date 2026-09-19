import { apiGet, catalogCacheOptions, withFallback } from './client';
import type { Tag } from './types';

interface FetchOptions {
  signal?: AbortSignal;
  cache?: RequestCache;
}

/**
 * GET /tags — no show endpoint exists; tags are filters, not detail pages.
 * Returns [] if the API fails. Never throws.
 */
export async function getTags(options: FetchOptions = {}): Promise<Tag[]> {
  return withFallback('GET /tags', [], async () => {
    const response = await apiGet<{ data: Tag[] }>('/tags', {
      signal: options.signal,
      ...catalogCacheOptions(options.cache),
    });

    return response.data;
  });
}
