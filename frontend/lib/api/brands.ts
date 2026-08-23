import { apiGet } from './client';
import type { Brand, SingleResponse } from './types';

interface FetchOptions {
  signal?: AbortSignal;
  cache?: RequestCache;
}

/** GET /brands — active brands only. */
export async function getBrands(options: FetchOptions = {}): Promise<Brand[]> {
  const response = await apiGet<{ data: Brand[] }>('/brands', {
    signal: options.signal,
    cache: options.cache,
  });

  return response.data;
}

/** GET /brands/{slug} — throws ApiError(404) if unknown/inactive. */
export async function getBrand(slug: string, options: FetchOptions = {}): Promise<Brand> {
  const response = await apiGet<SingleResponse<Brand>>(`/brands/${encodeURIComponent(slug)}`, {
    signal: options.signal,
    cache: options.cache,
  });

  return response.data;
}
