import { apiGet } from './client';
import type { Tag } from './types';

interface FetchOptions {
  signal?: AbortSignal;
  cache?: RequestCache;
}

/** GET /tags — no show endpoint exists; tags are filters, not detail pages. */
export async function getTags(options: FetchOptions = {}): Promise<Tag[]> {
  const response = await apiGet<{ data: Tag[] }>('/tags', {
    signal: options.signal,
    cache: options.cache,
  });

  return response.data;
}
