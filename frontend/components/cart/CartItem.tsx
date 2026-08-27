'use client';

import Image from 'next/image';
import { ProductImagePlaceholder } from '@/components/product/ProductImagePlaceholder';
import { MAX_CART_ITEM_QUANTITY } from '@/lib/cart/reducer';
import type { CartItem as CartItemType } from '@/lib/cart/types';
import { formatPrice } from '@/lib/utils/format';
import { useCart } from './CartProvider';

export function CartItemRow({ item }: { item: CartItemType }) {
  const { increment, decrement, removeItem } = useCart();

  return (
    <li className="flex gap-3 py-3 first:pt-0">
      <div className="relative h-16 w-16 shrink-0 overflow-hidden rounded-xl border border-border">
        {item.imageUrl ? (
          <Image src={item.imageUrl} alt={item.name} fill sizes="64px" className="object-cover" />
        ) : (
          <ProductImagePlaceholder className="h-full w-full" />
        )}
      </div>

      <div className="flex flex-1 flex-col gap-1">
        <p className="line-clamp-2 text-sm font-medium text-ink">{item.name}</p>
        {/* Prix affiché uniquement pour l'expérience d'achat — jamais
            transmis comme prix fiable au checkout, Laravel recalcule
            toujours depuis products.price. */}
        <div className="flex items-baseline justify-between gap-2">
          <p className="text-xs text-ink-muted">
            {formatPrice(item.price)} × {item.quantity}
          </p>
          <p className="text-sm font-semibold text-ink">
            {formatPrice(Number.parseFloat(item.price) * item.quantity)}
          </p>
        </div>

        <div className="mt-1 flex items-center justify-between">
          <div className="flex items-center gap-1 rounded-full border border-border">
            <button
              type="button"
              onClick={() => decrement(item.productId)}
              aria-label={`Diminuer la quantité de ${item.name}`}
              className="flex h-7 w-7 items-center justify-center text-ink hover:bg-brand-50"
            >
              −
            </button>
            <span className="w-6 text-center text-sm font-medium text-ink" aria-live="polite">
              {item.quantity}
            </span>
            <button
              type="button"
              onClick={() => increment(item.productId)}
              disabled={item.quantity >= MAX_CART_ITEM_QUANTITY}
              aria-label={`Augmenter la quantité de ${item.name}`}
              className="flex h-7 w-7 items-center justify-center text-ink hover:bg-brand-50 disabled:opacity-40"
            >
              +
            </button>
          </div>

          <button
            type="button"
            onClick={() => removeItem(item.productId)}
            aria-label={`Retirer ${item.name} du panier`}
            className="text-xs font-medium text-ink-muted underline hover:text-[color:var(--color-danger)]"
          >
            Supprimer
          </button>
        </div>
      </div>
    </li>
  );
}
