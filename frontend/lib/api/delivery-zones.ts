import { apiGet } from './client';
import type { DeliveryZone } from './types';

/**
 * GET /delivery-zones — not paginated (small, bounded reference list,
 * same convention as stores/brands/tags), so the envelope is
 * `{ data: DeliveryZone[] }` rather than the paginated shape products.ts
 * unwraps. Only active zones are ever returned by the API.
 */
export async function getDeliveryZones(): Promise<DeliveryZone[]> {
  const response = await apiGet<{ data: DeliveryZone[] }>('/delivery-zones');

  return response.data;
}
