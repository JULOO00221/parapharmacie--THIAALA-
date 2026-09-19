'use client';

import { useEffect, useReducer, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { CTA } from '@/components/ui/cta';
import { MinusIcon, PlusIcon, WhatsAppIcon } from '@/components/ui/icons';
import { MAX_CART_ITEM_QUANTITY } from '@/lib/cart/reducer';
import type { Product } from '@/lib/api/types';
import { WHATSAPP_LINK_PROPS, whatsappHref } from '@/lib/config/contact';
import { cn } from '@/lib/utils/cn';
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

  // Commander sur WhatsApp : au même niveau que le panier (DESIGN.md §4.2).
  // Le message reprend le produit, sa référence et la quantité choisie ;
  // pour un produit indisponible, il demande quand il reviendra.
  const whatsappMessage = product.available
    ? `Bonjour, je souhaite commander : ${product.name} (réf. ${product.sku}), quantité ${quantity}.`
    : `Bonjour, le produit ${product.name} (réf. ${product.sku}) est indiqué indisponible. Quand sera-t-il de retour ?`;
  const whatsappButton = (
    <a href={whatsappHref(whatsappMessage)} {...WHATSAPP_LINK_PROPS} className={cn(CTA.secondary, 'w-full')}>
      <WhatsAppIcon className="h-[18px] w-[18px]" />
      {product.available ? 'Commander sur WhatsApp' : 'Demander sur WhatsApp'}
    </a>
  );

  if (!product.available) {
    return (
      <div className="flex flex-col gap-3">
        <button type="button" disabled className={cn(CTA.primary, 'w-full cursor-not-allowed opacity-50')}>
          Indisponible
        </button>
        {whatsappButton}
      </div>
    );
  }

  const stepperButton =
    'flex h-11 w-11 items-center justify-center rounded-full text-vert transition-colors hover:bg-ivoire disabled:opacity-35 disabled:hover:bg-transparent';

  return (
    <>
      <div className="flex flex-col gap-3 lg:gap-3.5">
        <div ref={ctaRef} className="flex items-center gap-3 lg:gap-3.5">
          <div className="flex h-[52px] shrink-0 items-center rounded-full border border-bordure-forte bg-blanc px-1 sm:h-14 sm:px-1.5">
            <button
              type="button"
              onClick={() => setQuantity((q) => Math.max(1, q - 1))}
              disabled={quantity <= 1}
              aria-label="Diminuer la quantité"
              className={stepperButton}
            >
              <MinusIcon className="h-4 w-4" />
            </button>
            <span className="w-[30px] text-center text-base font-semibold text-encre sm:w-[38px] sm:text-[17px]" aria-live="polite">
              <span className="sr-only">Quantité : </span>
              {quantity}
            </span>
            <button
              type="button"
              onClick={() => setQuantity((q) => Math.min(MAX_CART_ITEM_QUANTITY, q + 1))}
              disabled={quantity >= MAX_CART_ITEM_QUANTITY}
              aria-label="Augmenter la quantité"
              className={stepperButton}
            >
              <PlusIcon className="h-4 w-4" />
            </button>
          </div>

          <button type="button" onClick={handleAdd} className={cn(CTA.primary, 'min-w-0 flex-1 px-4')}>
            Ajouter au panier
          </button>
        </div>

        {whatsappButton}
      </div>

      {showStickyBar &&
        stickyBarPortalTarget &&
        createPortal(
          // Barre d'achat collée en bas, mobile uniquement (DESIGN.md §3).
          <div className="sticky bottom-0 z-30 border-t border-bordure bg-blanc lg:hidden">
            <div className="mx-auto flex min-h-[78px] max-w-lg items-center gap-3 px-4 py-3">
              <div className="flex shrink-0 flex-col gap-0.5">
                <span className="text-[11.5px] text-texte-discret">{quantity > 1 ? `Total · ${quantity} articles` : 'Total'}</span>
                <span className="font-titre text-xl text-vert">
                  {formatPrice(Number.parseFloat(product.price) * quantity)}
                </span>
              </div>
              <button type="button" onClick={handleAdd} className={cn(CTA.primary, 'min-w-0 flex-1 px-4')}>
                Ajouter au panier
              </button>
            </div>
          </div>,
          stickyBarPortalTarget
        )}
    </>
  );
}
