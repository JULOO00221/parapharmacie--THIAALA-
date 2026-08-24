import type { CartItem, CartState } from './types';

/**
 * UX-level ceiling only, mirrored from OrderService::MAX_QUANTITY_PER_ITEM
 * (Laravel). This does NOT replace server validation — Laravel remains the
 * final authority and re-checks this same bound (plus real stock) at
 * checkout regardless of what the client ever allowed.
 */
export const MAX_CART_ITEM_QUANTITY = 50;

export type CartAction =
  | { type: 'ADD_ITEM'; item: Omit<CartItem, 'quantity'>; quantity?: number }
  | { type: 'REMOVE_ITEM'; productId: number }
  | { type: 'UPDATE_QUANTITY'; productId: number; quantity: number }
  | { type: 'INCREMENT'; productId: number }
  | { type: 'DECREMENT'; productId: number }
  | { type: 'CLEAR_CART' }
  | { type: 'HYDRATE'; state: CartState };

export const initialCartState: CartState = { items: [] };

function clampQuantity(quantity: number): number {
  return Math.min(Math.max(Math.round(quantity), 1), MAX_CART_ITEM_QUANTITY);
}

export function cartReducer(state: CartState, action: CartAction): CartState {
  switch (action.type) {
    case 'ADD_ITEM': {
      const quantity = clampQuantity(action.quantity ?? 1);
      const existing = state.items.find((item) => item.productId === action.item.productId);

      if (!existing) {
        return { items: [...state.items, { ...action.item, quantity }] };
      }

      return {
        items: state.items.map((item) =>
          item.productId === action.item.productId
            ? { ...item, quantity: clampQuantity(item.quantity + quantity) }
            : item
        ),
      };
    }

    case 'REMOVE_ITEM':
      return { items: state.items.filter((item) => item.productId !== action.productId) };

    case 'UPDATE_QUANTITY':
      // Quantité toujours clampée entre 1 et MAX — jamais 0 via cette
      // action, même si demandé : REMOVE_ITEM est le seul moyen de
      // retirer une ligne.
      return {
        items: state.items.map((item) =>
          item.productId === action.productId
            ? { ...item, quantity: clampQuantity(action.quantity) }
            : item
        ),
      };

    case 'INCREMENT':
      return {
        items: state.items.map((item) =>
          item.productId === action.productId
            ? { ...item, quantity: clampQuantity(item.quantity + 1) }
            : item
        ),
      };

    case 'DECREMENT': {
      const target = state.items.find((item) => item.productId === action.productId);
      if (!target) return state;

      if (target.quantity <= 1) {
        return { items: state.items.filter((item) => item.productId !== action.productId) };
      }

      return {
        items: state.items.map((item) =>
          item.productId === action.productId ? { ...item, quantity: item.quantity - 1 } : item
        ),
      };
    }

    case 'CLEAR_CART':
      return { items: [] };

    case 'HYDRATE':
      return action.state;

    default:
      return state;
  }
}
