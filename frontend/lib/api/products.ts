import { apiGet } from './client';
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

/** GET /products — returns the full paginated envelope (data + links + meta). */
export async function getProducts(
  filters: ProductFilters = {},
  options: FetchOptions = {}
): Promise<PaginatedResponse<Product>> {
  return apiGet<PaginatedResponse<Product>>('/products', {
    params: filters,
    signal: options.signal,
    cache: options.cache,
  });
}

/** GET /products/{slug} — returns the unwrapped product, or throws ApiError(404) if unknown/inactive. */
export async function getProduct(slug: string, options: FetchOptions = {}): Promise<Product> {
  const response = await apiGet<SingleResponse<Product>>(`/products/${encodeURIComponent(slug)}`, {
    signal: options.signal,
    cache: options.cache,
  });

  return response.data;
}
