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
  /** `card` : prix en Fraunces de la carte produit (DESIGN.md §2). */
  size?: 'md' | 'lg' | 'card';
}) {
  const priceClass = {
    md: 'text-base font-semibold',
    lg: 'text-2xl font-bold',
    card: 'font-titre text-[17px] leading-tight xl:text-[21px]',
  }[size];
  const discounted = isDiscounted(price, compareAtPrice);

  return (
    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
      <span className={cn(priceClass, 'whitespace-nowrap', discounted ? 'text-promo-600' : size === 'card' && 'text-vert')}>
        {formatPrice(price)}
      </span>
      {discounted && compareAtPrice && (
        <span className={cn('text-texte-discret line-through', size === 'card' ? 'text-xs' : 'text-sm')}>{formatPrice(compareAtPrice)}</span>
      )}
    </div>
  );
}
