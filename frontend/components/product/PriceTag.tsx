import { formatPrice } from '@/lib/utils/format';

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

  return (
    <div className="flex items-baseline gap-2">
      <span className={priceClass}>{formatPrice(price)}</span>
      {compareAtPrice && <span className="text-sm text-ink-muted line-through">{formatPrice(compareAtPrice)}</span>}
    </div>
  );
}
