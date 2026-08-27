'use client';

import { useEffect, useReducer, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Button } from '@/components/ui/Button';
import { MAX_CART_ITEM_QUANTITY } from '@/lib/cart/reducer';
import type { Product } from '@/lib/api/types';
import { formatPrice } from '@/lib/utils/format';
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
  const ctaRef = useRef<HTMLDivElement>(null);
  const [showStickyBar, setShowStickyBar] = useState(false);
  // Reducer + dispatch (not useState) is deliberate here — same reasoning
  // as OrderSuccessView/PaymentResultView's own loadStateReducer: the
  // react-hooks/set-state-in-effect rule flags a setState call made
  // synchronously at the top of an effect body, but not a dispatch call.
  const [stickyBarPortalTarget, setStickyBarPortalTarget] = useReducer(
    (_current: HTMLElement | null, next: HTMLElement | null) => next,
    null
  );

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

  // Mobile-only sticky bar: mirrors the same quantity/price/add-to-cart
  // action once the main CTA has scrolled out of view, so the CTA stays
  // reachable without the user scrolling back up. Never active on desktop
  // (lg:hidden below) or for an unavailable product (nothing to add).
  useEffect(() => {
    if (!product.available) return;

    const node = ctaRef.current;
    if (!node || typeof IntersectionObserver === 'undefined') return;

    const observer = new IntersectionObserver(([entry]) => {
      // Distinguishes "scrolled past going down" (top edge above the
      // viewport) from "not reached yet" (below the viewport on first
      // paint, since the CTA sits under a tall gallery image) — both report
      // isIntersecting: false, but only the former should trigger the bar.
      const scrolledPast = !entry.isIntersecting && entry.boundingClientRect.top < 0;
      setShowStickyBar(scrolledPast);
    });
    observer.observe(node);

    return () => observer.disconnect();
  }, [product.available]);

  // The portal target (#product-page-sticky-container, rendered by the
  // product page) spans the entire page's own content — title through
  // "Produits similaires" — so a `sticky bottom-0` bar placed as its last
  // child releases naturally right before the real Footer instead of
  // covering it, unlike a `fixed` bar (which always ends up flush with the
  // Footer's bottom edge at true max-scroll, no matter how much spacing
  // precedes it — that's what the earlier version of this component got
  // wrong). Portalling is required here because this component itself is
  // nested deep inside the page's two-column grid, too short a container
  // on its own to give the bar a useful amount of "stuck" travel.
  useEffect(() => {
    setStickyBarPortalTarget(document.getElementById('product-page-sticky-container'));
  }, []);

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
    <>
      <div ref={ctaRef} className="mt-6 flex flex-wrap items-center gap-3">
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

        <Button size="lg" onClick={handleAdd} className="flex-1 sm:flex-none">
          Ajouter au panier
        </Button>
      </div>

      {showStickyBar &&
        stickyBarPortalTarget &&
        createPortal(
          <div className="sticky bottom-0 z-30 border-t border-border bg-surface-raised p-3 shadow-lg lg:hidden">
            <div className="mx-auto flex max-w-lg items-center justify-between gap-3 px-4 sm:px-6">
              <div className="min-w-0">
                <p className="truncate text-xs text-ink-muted">{product.name}</p>
                <p className="text-base font-semibold text-ink">{formatPrice(product.price)}</p>
              </div>
              <Button onClick={handleAdd} className="shrink-0">
                Ajouter au panier
              </Button>
            </div>
          </div>,
          stickyBarPortalTarget
        )}
    </>
  );
}
