'use client';

import { useCart } from './CartProvider';

export function CartButton() {
  const { itemCount, openCart } = useCart();

  return (
    <button
      type="button"
      onClick={openCart}
      aria-label={itemCount > 0 ? `Ouvrir le panier, ${itemCount} article${itemCount > 1 ? 's' : ''}` : 'Ouvrir le panier, vide'}
      className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-border text-ink hover:bg-brand-50"
    >
      <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.7" className="h-5 w-5">
        <path d="M4 6h1.6l1 8.4a1.6 1.6 0 0 0 1.6 1.4h6.2a1.6 1.6 0 0 0 1.6-1.3l1-6.5H6.2" strokeLinecap="round" strokeLinejoin="round" />
        <circle cx="8.5" cy="17" r="1" fill="currentColor" stroke="none" />
        <circle cx="14.5" cy="17" r="1" fill="currentColor" stroke="none" />
      </svg>

      {itemCount > 0 && (
        <span
          aria-hidden="true"
          className="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-accent-600 px-1 text-[11px] font-semibold text-white"
        >
          {itemCount > 99 ? '99+' : itemCount}
        </span>
      )}
    </button>
  );
}
