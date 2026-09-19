import { apiGet, catalogCacheOptions, withFallback } from './client';
import type { Category, SingleResponse } from './types';

interface FetchOptions {
  signal?: AbortSignal;
  cache?: RequestCache;
}

/**
 * GET /categories — active categories only, with parent/children
 * eager-loaded, or [] if the API fails. Never throws.
 */
export async function getCategories(options: FetchOptions = {}): Promise<Category[]> {
  return withFallback('GET /categories', [], async () => {
    const response = await apiGet<{ data: Category[] }>('/categories', {
      signal: options.signal,
      ...catalogCacheOptions(options.cache),
    });

    return response.data;
  });
}

/** GET /categories/{slug} — null if unknown/inactive (404) or the API fails. Never throws. */
export async function getCategory(slug: string, options: FetchOptions = {}): Promise<Category | null> {
  return withFallback(`GET /categories/${slug}`, null, async () => {
    const response = await apiGet<SingleResponse<Category>>(`/categories/${encodeURIComponent(slug)}`, {
      signal: options.signal,
      ...catalogCacheOptions(options.cache),
    });

    return response.data;
  });
}
