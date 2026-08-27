import Image from 'next/image';
import Link from 'next/link';
import { AddToCartButton } from '@/components/cart/AddToCartButton';
import type { Product } from '@/lib/api/types';
import { discountPercent } from '@/lib/utils/pricing';
import { PriceTag } from './PriceTag';
import { ProductBadges } from './ProductBadges';
import { ProductImagePlaceholder } from './ProductImagePlaceholder';
import { StockBadge } from './StockBadge';

export function ProductCard({ product }: { product: Product }) {
  const percentOff = discountPercent(product.price, product.compare_at_price);

  return (
    <Link
      href={`/produits/${product.slug}`}
      className="group flex flex-col overflow-hidden rounded-2xl border border-border bg-surface-raised transition-shadow hover:shadow-md"
    >
      <div className="relative aspect-square w-full overflow-hidden">
        {product.primary_image ? (
          <Image
            src={product.primary_image.url}
            alt={product.primary_image.alt_text ?? product.name}
            fill
            sizes="(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw"
            className="object-cover transition-transform duration-300 group-hover:scale-105"
          />
        ) : (
          <ProductImagePlaceholder className="h-full w-full" />
        )}

        <ProductBadges featured={product.featured} percentOff={percentOff} />
      </div>

      <div className="flex flex-1 flex-col gap-2 p-4">
        {product.brand && <p className="text-xs font-medium uppercase tracking-wide text-ink-muted">{product.brand.name}</p>}
        <h3 className="line-clamp-2 text-sm font-semibold text-ink">{product.name}</h3>

        <div className="mt-auto space-y-1.5 pt-2">
          <PriceTag price={product.price} compareAtPrice={product.compare_at_price} />
          <StockBadge status={product.stock_status} />
        </div>

        <AddToCartButton product={product} className="w-full" />
      </div>
    </Link>
  );
}
