'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { MAX_CART_ITEM_QUANTITY } from '@/lib/cart/reducer';
import type { Product } from '@/lib/api/types';
import { useCart } from './CartProvider';

/**
 * `product.available` is only an indicative UX signal (computed by
 * ProductService::availability() from stock at the time this page was
 * rendered) — Laravel re-validates real stock and product state again at
 * checkout regardless of what's shown here.
 */
export function ProductAddToCart({ product }: { product: Product }) {
  const { addItem, openCart } = useCart();
  const [quantity, setQuantity] = useState(1);

  function handleAdd() {
    if (!product.available) return;

    addItem(
      {
        productId: product.id,
        name: product.name,
        slug: product.slug,
        price: product.price,
        imageUrl: product.primary_image?.url ?? null,
        sku: product.sku,
      },
      quantity
    );

    setQuantity(1);
    openCart();
  }

  if (!product.available) {
    return (
      <div className="mt-6">
        <Button variant="outline" size="lg" disabled className="w-full sm:w-auto">
          Indisponible
        </Button>
        <p className="mt-2 text-xs text-ink-muted">
          Ce produit n&apos;est pas disponible actuellement.
        </p>
      </div>
    );
  }

  return (
    <div className="mt-6 flex flex-wrap items-center gap-3">
      <div className="flex items-center gap-1 rounded-full border border-border">
        <button
          type="button"
          onClick={() => setQuantity((q) => Math.max(1, q - 1))}
          disabled={quantity <= 1}
          aria-label="Diminuer la quantité"
          className="flex h-10 w-10 items-center justify-center text-ink hover:bg-brand-50 disabled:opacity-40"
        >
          −
        </button>
        <span className="w-8 text-center font-medium text-ink" aria-live="polite">
          {quantity}
        </span>
        <button
          type="button"
          onClick={() => setQuantity((q) => Math.min(MAX_CART_ITEM_QUANTITY, q + 1))}
          disabled={quantity >= MAX_CART_ITEM_QUANTITY}
          aria-label="Augmenter la quantité"
          className="flex h-10 w-10 items-center justify-center text-ink hover:bg-brand-50 disabled:opacity-40"
        >
          +
        </button>
      </div>

      <Button size="lg" onClick={handleAdd}>
        Ajouter au panier
      </Button>
    </div>
  );
}
