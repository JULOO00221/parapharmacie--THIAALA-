'use client';

import { useState } from 'react';
import type { MouseEvent } from 'react';
import { CheckIcon, PlusIcon } from '@/components/ui/icons';
import type { Product } from '@/lib/api/types';
import { cn } from '@/lib/utils/cn';
import { useCart } from './CartProvider';

/**
 * Bouton rond « + » de ProductCard (DESIGN.md §2). Il est posé au-dessus du
 * lien étiré de la carte : son clic ne doit jamais déclencher la navigation
 * vers la fiche produit.
 */
export function AddToCartButton({ product, className }: { product: Product; className?: string }) {
  const { addItem } = useCart();
  const [justAdded, setJustAdded] = useState(false);

  function handleClick(event: MouseEvent<HTMLButtonElement>) {
    event.preventDefault();
    event.stopPropagation();

    if (!product.available || justAdded) return;

    addItem({
      productId: product.id,
      name: product.name,
      slug: product.slug,
      price: product.price,
      imageUrl: product.primary_image?.url ?? null,
      sku: product.sku,
    });

    setJustAdded(true);
    window.setTimeout(() => setJustAdded(false), 1500);
  }

  return (
    <button
      type="button"
      onClick={handleClick}
      disabled={!product.available}
      aria-label={
        justAdded
          ? `${product.name} ajouté au panier`
          : product.available
            ? `Ajouter ${product.name} au panier`
            : `${product.name} indisponible`
      }
      className={cn(
        'flex h-11 w-11 shrink-0 items-center justify-center rounded-full border transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-vert disabled:cursor-not-allowed disabled:opacity-40',
        justAdded
          ? 'border-vert bg-vert text-white'
          : 'border-bordure-forte bg-ivoire text-vert hover:border-vert hover:bg-vert hover:text-white',
        className,
      )}
    >
      {justAdded ? <CheckIcon className="h-[18px] w-[18px]" /> : <PlusIcon className="h-[19px] w-[19px]" />}
    </button>
  );
}
