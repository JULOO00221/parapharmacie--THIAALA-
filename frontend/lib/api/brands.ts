import { apiGet, catalogCacheOptions, withFallback } from './client';
import type { Brand, SingleResponse } from './types';

interface FetchOptions {
  signal?: AbortSignal;
  cache?: RequestCache;
}

/** GET /brands — active brands only, or [] if the API fails. Never throws. */
export async function getBrands(options: FetchOptions = {}): Promise<Brand[]> {
  return withFallback('GET /brands', [], async () => {
    const response = await apiGet<{ data: Brand[] }>('/brands', {
      signal: options.signal,
      ...catalogCacheOptions(options.cache),
    });

    return response.data;
  });
}

/** GET /brands/{slug} — null if unknown/inactive (404) or the API fails. Never throws. */
export async function getBrand(slug: string, options: FetchOptions = {}): Promise<Brand | null> {
  return withFallback(`GET /brands/${slug}`, null, async () => {
    const response = await apiGet<SingleResponse<Brand>>(`/brands/${encodeURIComponent(slug)}`, {
      signal: options.signal,
      ...catalogCacheOptions(options.cache),
    });

    return response.data;
  });
}
