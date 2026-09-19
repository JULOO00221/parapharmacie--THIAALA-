import { cn } from '@/lib/utils/cn';
import { formatPrice } from '@/lib/utils/format';
import { isDiscounted } from '@/lib/utils/pricing';

export function PriceTag({
  price,
  compareAtPrice,
  size = 'md',
}: {
  price: string;
  compareAtPrice: string | null;
  /** `card` : prix en Fraunces de la carte produit ; `lg` : celui de la fiche produit (DESIGN.md §2-3). */
  size?: 'md' | 'lg' | 'card';
}) {
  const priceClass = {
    md: 'text-base font-semibold',
    lg: 'font-titre text-[32px] leading-none lg:text-[40px]',
    card: 'font-titre text-[17px] leading-tight xl:text-[21px]',
  }[size];
  const discounted = isDiscounted(price, compareAtPrice);

  return (
    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
      <span className={cn(priceClass, 'whitespace-nowrap', discounted ? 'text-promo-600' : size !== 'md' && 'text-vert')}>
        {formatPrice(price)}
      </span>
      {discounted && compareAtPrice && (
        <span className={cn('text-texte-discret line-through', { md: 'text-sm', card: 'text-xs', lg: 'text-base lg:text-lg' }[size])}>{formatPrice(compareAtPrice)}</span>
      )}
    </div>
  );
}
