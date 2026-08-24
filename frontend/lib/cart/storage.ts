import { initialCartState } from './reducer';
import type { CartState } from './types';

export const CART_STORAGE_KEY = 'tambacounda-cosmetix-cart';

/**
 * Bumped if the stored shape ever changes incompatibly — lets loadCart()
 * discard an old-shape payload instead of crashing on it.
 */
const CART_STORAGE_VERSION = 1;

interface StoredCart {
  version: number;
  state: CartState;
}

function isValidCartState(value: unknown): value is CartState {
  return (
    typeof value === 'object' &&
    value !== null &&
    Array.isArray((value as CartState).items) &&
    (value as CartState).items.every(
      (item) =>
        typeof item === 'object' &&
        item !== null &&
        typeof item.productId === 'number' &&
        typeof item.quantity === 'number'
    )
  );
}

/**
 * Never called during server rendering or the first client render (see
 * CartProvider) — only from an effect, after mount, so the first paint
 * never depends on localStorage and can't hydration-mismatch.
 */
export function loadCart(): CartState {
  if (typeof window === 'undefined') {
    return initialCartState;
  }

  try {
    const raw = window.localStorage.getItem(CART_STORAGE_KEY);
    if (!raw) return initialCartState;

    const parsed = JSON.parse(raw) as Partial<StoredCart>;

    if (parsed.version !== CART_STORAGE_VERSION || !isValidCartState(parsed.state)) {
      return initialCartState;
    }

    return parsed.state;
  } catch {
    // JSON invalide, storage indisponible, ou forme inattendue : on repart
    // d'un panier vide plutôt que de faire planter le storefront.
    return initialCartState;
  }
}

export function saveCart(state: CartState): void {
  if (typeof window === 'undefined') return;

  try {
    const payload: StoredCart = { version: CART_STORAGE_VERSION, state };
    window.localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(payload));
  } catch {
    // Quota dépassé, storage désactivé (navigation privée stricte), etc. —
    // échec silencieux : le panier reste utilisable en mémoire pour la
    // session en cours, seule la persistance est perdue.
  }
}
