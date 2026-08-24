import { beforeEach, describe, expect, it } from 'vitest';
import { initialCartState } from './reducer';
import { CART_STORAGE_KEY, loadCart, saveCart } from './storage';
import type { CartState } from './types';

const CART: CartState = {
  items: [
    { productId: 1, quantity: 2, name: 'Savon noir', slug: 'savon-noir', price: '1500.00', imageUrl: null, sku: 'TC-0001' },
  ],
};

describe('cart storage', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('loadCart returns an empty cart when nothing is stored', () => {
    expect(loadCart()).toEqual(initialCartState);
  });

  it('saveCart then loadCart round-trips the same state (valid localStorage content)', () => {
    saveCart(CART);

    expect(loadCart()).toEqual(CART);
  });

  it('a second loadCart call (simulating a page refresh) still returns the persisted cart', () => {
    saveCart(CART);

    // Nouvel appel indépendant, comme si la page venait d'être rechargée —
    // aucun état en mémoire n'est réutilisé, tout repasse par le storage.
    expect(loadCart()).toEqual(CART);
    expect(loadCart()).toEqual(CART);
  });

  it('loadCart falls back to an empty cart on invalid JSON instead of throwing', () => {
    window.localStorage.setItem(CART_STORAGE_KEY, '{not valid json');

    expect(() => loadCart()).not.toThrow();
    expect(loadCart()).toEqual(initialCartState);
  });

  it('loadCart falls back to an empty cart when the stored shape is unexpected', () => {
    window.localStorage.setItem(CART_STORAGE_KEY, JSON.stringify({ version: 1, state: { items: 'not-an-array' } }));

    expect(loadCart()).toEqual(initialCartState);
  });

  it('loadCart falls back to an empty cart on a version mismatch', () => {
    window.localStorage.setItem(CART_STORAGE_KEY, JSON.stringify({ version: 999, state: CART }));

    expect(loadCart()).toEqual(initialCartState);
  });

  it('saveCart writes under the stable, dedicated storage key', () => {
    saveCart(CART);

    expect(window.localStorage.getItem(CART_STORAGE_KEY)).not.toBeNull();
    expect(CART_STORAGE_KEY).toBe('tambacounda-cosmetix-cart');
  });
});
