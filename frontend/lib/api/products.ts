import { apiGet, catalogCacheOptions, withFallback } from './client';
import type { PaginatedResponse, Product, SingleResponse } from './types';

export type ProductSort = 'name_asc' | 'name_desc' | 'price_asc' | 'price_desc' | 'newest' | 'featured_first';

export interface ProductFilters {
  q?: string;
  category?: string;
  brand?: string;
  /** Comma-separated tag slugs, matching the Laravel endpoint's contract. */
  tags?: string;
  price_min?: number;
  price_max?: number;
  featured?: boolean;
  in_stock?: boolean;
  sort?: ProductSort;
  per_page?: number;
  page?: number;
  // Structurally compatible with QueryParams so it can be passed to apiGet
  // without an unsafe cast, while still getting autocomplete on the fields above.
  [key: string]: string | number | boolean | undefined | null;
}

interface FetchOptions {
  signal?: AbortSignal;
  cache?: RequestCache;
}

/** Enveloppe paginée vide — valeur par défaut quand l'API est indisponible. */
function emptyPage<T>(filters: ProductFilters): PaginatedResponse<T> {
  return {
    data: [],
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: 1,
      from: null,
      last_page: 1,
      links: [],
      path: '',
      per_page: filters.per_page ?? 0,
      to: null,
      total: 0,
    },
  };
}

/**
 * GET /products — returns the full paginated envelope (data + links + meta),
 * or an empty page if the API fails. Never throws.
 */
export async function getProducts(
  filters: ProductFilters = {},
  options: FetchOptions = {}
): Promise<PaginatedResponse<Product>> {
  return withFallback('GET /products', emptyPage<Product>(filters), () =>
    apiGet<PaginatedResponse<Product>>('/products', {
      params: filters,
      signal: options.signal,
      ...catalogCacheOptions(options.cache),
    })
  );
}

/**
 * GET /products/{slug} — returns the unwrapped product, or null if it is
 * unknown/inactive (404) or the API fails. Never throws.
 */
export async function getProduct(slug: string, options: FetchOptions = {}): Promise<Product | null> {
  return withFallback(`GET /products/${slug}`, null, async () => {
    const response = await apiGet<SingleResponse<Product>>(`/products/${encodeURIComponent(slug)}`, {
      signal: options.signal,
      ...catalogCacheOptions(options.cache),
    });

    return response.data;
  });
}
