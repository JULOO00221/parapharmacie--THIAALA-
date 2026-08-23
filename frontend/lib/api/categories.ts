import { apiGet } from './client';
import type { Category, SingleResponse } from './types';

interface FetchOptions {
  signal?: AbortSignal;
  cache?: RequestCache;
}

/** GET /categories — active categories only, with parent/children eager-loaded. */
export async function getCategories(options: FetchOptions = {}): Promise<Category[]> {
  const response = await apiGet<{ data: Category[] }>('/categories', {
    signal: options.signal,
    cache: options.cache,
  });

  return response.data;
}

/** GET /categories/{slug} — throws ApiError(404) if unknown/inactive. */
export async function getCategory(slug: string, options: FetchOptions = {}): Promise<Category> {
  const response = await apiGet<SingleResponse<Category>>(`/categories/${encodeURIComponent(slug)}`, {
    signal: options.signal,
    cache: options.cache,
  });

  return response.data;
}
