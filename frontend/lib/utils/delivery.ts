import type { DeliveryZone } from '@/lib/api/types';

/** Least expensive active delivery zone, or null when there is none. */
export function cheapestDeliveryZone(zones: DeliveryZone[]): DeliveryZone | null {
  return zones.reduce<DeliveryZone | null>(
    (best, zone) => (best === null || Number.parseFloat(zone.fee) < Number.parseFloat(best.fee) ? zone : best),
    null,
  );
}
