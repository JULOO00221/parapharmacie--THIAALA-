'use client';

import { BagIcon } from '@/components/ui/icons';
import { cn } from '@/lib/utils/cn';
import { useCart } from './CartProvider';

/**
 * `bordered` : pastille cerclée de l'en-tête desktop. Sans bordure sur
 * mobile, où l'en-tête est plus serré. La cible reste d'au moins 44 × 44.
 */
export function CartButton({ bordered = true, className }: { bordered?: boolean; className?: string }) {
  const { itemCount, openCart } = useCart();

  return (
    <button
      type="button"
      onClick={openCart}
      aria-label={itemCount > 0 ? `Ouvrir le panier, ${itemCount} article${itemCount > 1 ? 's' : ''}` : 'Ouvrir le panier, vide'}
      className={cn(
        'relative flex shrink-0 items-center justify-center rounded-full text-encre transition-colors hover:bg-ivoire',
        bordered ? 'h-[46px] w-[46px] border border-bordure' : 'h-11 w-11',
        className,
      )}
    >
      <BagIcon className="h-[19px] w-[19px]" />

      {itemCount > 0 && (
        <span
          aria-hidden="true"
          className="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-vert px-1 text-[11px] font-semibold text-white"
        >
          {itemCount > 99 ? '99+' : itemCount}
        </span>
      )}
    </button>
  );
}
