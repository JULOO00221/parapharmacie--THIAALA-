import { describe, expect, it } from 'vitest';
import { cheapestDeliveryZone } from './delivery';

describe('cheapestDeliveryZone', () => {
  it('returns the zone with the lowest fee', () => {
    const zones = [
      { id: 1, name: 'Autres', fee: '3000.00' },
      { id: 2, name: 'Tambacounda Ville', fee: '500.00' },
      { id: 3, name: 'Alentours', fee: '2000.00' },
    ];

    expect(cheapestDeliveryZone(zones)?.name).toBe('Tambacounda Ville');
  });

  it('returns null without zones', () => {
    expect(cheapestDeliveryZone([])).toBeNull();
  });
});
