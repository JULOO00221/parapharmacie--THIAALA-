'use client';

import { createContext, useCallback, useContext, useEffect, useMemo, useReducer, useState } from 'react';
import type { ReactNode } from 'react';
import { cartReducer, initialCartState } from '@/lib/cart/reducer';
import { loadCart, saveCart } from '@/lib/cart/storage';
import type { CartItem } from '@/lib/cart/types';

interface CartContextValue {
  items: CartItem[];
  /** Sum of quantities — what the Header badge shows. */
  itemCount: number;
  /**
   * Display-only estimate (sum of price × quantity from the local
   * snapshot). Never sent to Laravel as an authoritative amount — the
   * server recomputes subtotal/delivery_fee/total from scratch at
   * checkout and never trusts a client-supplied figure.
   */
  subtotal: number;
  isOpen: boolean;
  openCart: () => void;
  closeCart: () => void;
  addItem: (item: Omit<CartItem, 'quantity'>, quantity?: number) => void;
  removeItem: (productId: number) => void;
  updateQuantity: (productId: number, quantity: number) => void;
  increment: (productId: number) => void;
  decrement: (productId: number) => void;
  clearCart: () => void;
}

const CartContext = createContext<CartContextValue | null>(null);

export function CartProvider({ children }: { children: ReactNode }) {
  const [state, dispatch] = useReducer(cartReducer, initialCartState);
  const [isOpen, setIsOpen] = useState(false);
  // Reste `false` pour le tout premier rendu serveur ET le tout premier
  // rendu client (avant montage) — le panier localStorage n'est chargé
  // qu'après, dans un effet, pour ne jamais provoquer de hydration
  // mismatch (le HTML serveur ne peut pas connaître le contenu du
  // localStorage du navigateur). Un useReducer trivial plutôt qu'un
  // useState : dispatch() dans un effet est idiomatique, alors que la
  // règle react-hooks/set-state-in-effect interdit un setState direct.
  const [isHydrated, hydrate] = useReducer(() => true, false);

  useEffect(() => {
    dispatch({ type: 'HYDRATE', state: loadCart() });
    hydrate();
  }, []);

  useEffect(() => {
    // Ne jamais écrire avant l'hydratation : sinon ce second effet,
    // exécuté lors du même passage de montage que celui ci-dessus,
    // écraserait le panier réel du localStorage avec l'état initial vide
    // avant même que HYDRATE n'ait eu l'occasion d'être appliqué.
    if (!isHydrated) return;
    saveCart(state);
  }, [state, isHydrated]);

  const addItem = useCallback((item: Omit<CartItem, 'quantity'>, quantity = 1) => {
    dispatch({ type: 'ADD_ITEM', item, quantity });
  }, []);

  const removeItem = useCallback((productId: number) => {
    dispatch({ type: 'REMOVE_ITEM', productId });
  }, []);

  const updateQuantity = useCallback((productId: number, quantity: number) => {
    dispatch({ type: 'UPDATE_QUANTITY', productId, quantity });
  }, []);

  const increment = useCallback((productId: number) => {
    dispatch({ type: 'INCREMENT', productId });
  }, []);

  const decrement = useCallback((productId: number) => {
    dispatch({ type: 'DECREMENT', productId });
  }, []);

  const clearCart = useCallback(() => {
    dispatch({ type: 'CLEAR_CART' });
  }, []);

  const openCart = useCallback(() => setIsOpen(true), []);
  const closeCart = useCallback(() => setIsOpen(false), []);

  const itemCount = useMemo(() => state.items.reduce((sum, item) => sum + item.quantity, 0), [state.items]);
  const subtotal = useMemo(
    () => state.items.reduce((sum, item) => sum + Number.parseFloat(item.price) * item.quantity, 0),
    [state.items]
  );

  const value = useMemo<CartContextValue>(
    () => ({
      items: state.items,
      itemCount,
      subtotal,
      isOpen,
      openCart,
      closeCart,
      addItem,
      removeItem,
      updateQuantity,
      increment,
      decrement,
      clearCart,
    }),
    [state.items, itemCount, subtotal, isOpen, openCart, closeCart, addItem, removeItem, updateQuantity, increment, decrement, clearCart]
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart(): CartContextValue {
  const context = useContext(CartContext);

  if (!context) {
    throw new Error('useCart must be used within a CartProvider');
  }

  return context;
}
