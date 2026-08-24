import { apiGet } from './client';
import type { Store } from './types';

/**
 * GET /stores — not paginated (small, bounded reference list, same
 * convention as brands/tags), so the envelope is `{ data: Store[] }`
 * rather than the paginated shape products.ts unwraps.
 */
export async function getStores(): Promise<Store[]> {
  const response = await apiGet<{ data: Store[] }>('/stores');

  return response.data;
}
