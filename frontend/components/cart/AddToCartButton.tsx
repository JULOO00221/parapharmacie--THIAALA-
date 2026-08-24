'use client';

import { useState } from 'react';
import type { MouseEvent } from 'react';
import { Button } from '@/components/ui/Button';
import type { Product } from '@/lib/api/types';
import { useCart } from './CartProvider';

/**
 * Used on ProductCard, which is itself an entire <Link> to the product
 * page — this button lives inside that link, so its click must never
 * bubble into a navigation.
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
    <Button
      type="button"
      variant={justAdded ? 'secondary' : 'primary'}
      size="sm"
      onClick={handleClick}
      disabled={!product.available}
      aria-label={product.available ? `Ajouter ${product.name} au panier` : `${product.name} indisponible`}
      className={className}
    >
      {justAdded ? 'Ajouté ✓' : 'Ajouter au panier'}
    </Button>
  );
}
