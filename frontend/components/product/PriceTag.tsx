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
  size?: 'md' | 'lg';
}) {
  const priceClass = size === 'lg' ? 'text-2xl font-bold' : 'text-base font-semibold';
  const discounted = isDiscounted(price, compareAtPrice);

  return (
    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
      <span className={cn(priceClass, discounted && 'text-promo-600')}>{formatPrice(price)}</span>
      {discounted && compareAtPrice && (
        <span className="text-sm text-ink-muted line-through">{formatPrice(compareAtPrice)}</span>
      )}
    </div>
  );
}
