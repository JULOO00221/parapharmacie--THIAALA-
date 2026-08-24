'use client';

import { useEffect, useRef } from 'react';
import { Button } from '@/components/ui/Button';
import { CartItemRow } from './CartItem';
import { useCart } from './CartProvider';
import { CartSummary } from './CartSummary';

export function CartDrawer() {
  const { items, isOpen, closeCart, subtotal } = useCart();
  const closeButtonRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!isOpen) return;

    closeButtonRef.current?.focus();

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') closeCart();
    }

    document.addEventListener('keydown', handleKeyDown);

    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [isOpen, closeCart]);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      <button type="button" aria-label="Fermer le panier" onClick={closeCart} className="absolute inset-0 bg-ink/40" />

      <div
        role="dialog"
        aria-modal="true"
        aria-label="Panier"
        className="relative flex h-full w-full max-w-md flex-col bg-surface-raised shadow-xl"
      >
        <div className="flex items-center justify-between border-b border-border px-4 py-4 sm:px-6">
          <h2 className="text-lg font-semibold text-ink">Mon panier</h2>
          <button
            ref={closeButtonRef}
            type="button"
            onClick={closeCart}
            aria-label="Fermer le panier"
            className="flex h-9 w-9 items-center justify-center rounded-full text-ink hover:bg-brand-50"
          >
            <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.7" className="h-5 w-5">
              <path d="M5 5l10 10M15 5 5 15" strokeLinecap="round" />
            </svg>
          </button>
        </div>

        {items.length === 0 ? (
          <div className="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
            <p className="text-ink-muted">Votre panier est vide.</p>
            <Button href="/produits" onClick={closeCart}>
              Continuer mes achats
            </Button>
          </div>
        ) : (
          <>
            <ul className="flex-1 overflow-y-auto px-4 py-2 sm:px-6">
              {items.map((item) => (
                <CartItemRow key={item.productId} item={item} />
              ))}
            </ul>

            <div className="border-t border-border px-4 py-4 sm:px-6">
              <CartSummary subtotal={subtotal} />
              <div className="mt-4 flex flex-col gap-2">
                <Button href="/panier" variant="outline" onClick={closeCart}>
                  Voir le panier
                </Button>
                <Button href="/commande" onClick={closeCart}>
                  Commander
                </Button>
              </div>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
