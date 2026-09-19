import Image from 'next/image';
import Link from 'next/link';
import { AddToCartButton } from '@/components/cart/AddToCartButton';
import type { Product } from '@/lib/api/types';
import { discountPercent } from '@/lib/utils/pricing';
import { PriceTag } from './PriceTag';
import { ProductBadges } from './ProductBadges';
import { ProductImagePlaceholder } from './ProductImagePlaceholder';

const STOCK_NOTICE: Partial<Record<Product['stock_status'], string>> = {
  low_stock: 'Stock faible',
  out_of_stock: 'Rupture de stock',
};

/**
 * Carte produit (DESIGN.md §2) : visuel carré sur ivoire foncé, marque en or,
 * nom, format, prix en Fraunces, bouton rond « + ».
 *
 * Toute la carte est cliquable via un lien étiré (le ::after du lien sur le
 * nom couvre la carte), mais le bouton « + » reste un <button> frère posé
 * au-dessus : jamais de bouton imbriqué dans un <a>.
 */
export function ProductCard({ product }: { product: Product }) {
  const percentOff = discountPercent(product.price, product.compare_at_price);
  const stockNotice = STOCK_NOTICE[product.stock_status];

  return (
    <article className="group relative flex flex-col overflow-hidden rounded-[14px] border border-bordure bg-blanc transition-colors hover:border-bordure-forte sm:rounded-[18px]">
      <div className="relative aspect-square w-full overflow-hidden bg-ivoire-fonce">
        {product.primary_image ? (
          <Image
            src={product.primary_image.url}
            alt={product.primary_image.alt_text ?? product.name}
            fill
            sizes="(min-width: 1280px) 300px, (min-width: 640px) 33vw, 50vw"
            className="object-cover transition-transform duration-300 group-hover:scale-[1.03]"
          />
        ) : (
          <ProductImagePlaceholder className="h-full w-full" />
        )}

        <ProductBadges featured={product.featured} percentOff={percentOff} />
      </div>

      <div className="flex flex-1 flex-col gap-1 px-[13px] pb-3.5 pt-3 sm:gap-[7px] sm:px-5 sm:pb-5 sm:pt-[18px]">
        {product.brand && (
          <p className="text-[9.5px] font-semibold uppercase tracking-[0.14em] text-or sm:text-[11px] sm:tracking-[0.16em]">
            {product.brand.name}
          </p>
        )}

        <h3 className="line-clamp-2 text-[13.5px] font-semibold leading-snug text-encre sm:text-base">
          <Link
            href={`/produits/${product.slug}`}
            className="after:absolute after:inset-0 after:content-[''] focus-visible:outline-none focus-visible:after:rounded-[inherit] focus-visible:after:outline-2 focus-visible:after:outline-offset-[-2px] focus-visible:after:outline-vert"
          >
            {product.name}
          </Link>
        </h3>

        {product.short_description && (
          <p className="line-clamp-1 text-xs text-texte-discret sm:text-[12.5px]">{product.short_description}</p>
        )}

        {stockNotice && (
          <p className={`text-xs font-medium ${product.stock_status === 'out_of_stock' ? 'text-danger' : 'text-warning'}`}>
            {stockNotice}
          </p>
        )}

        <div className="mt-auto flex flex-wrap items-center justify-between gap-x-2 gap-y-1 pt-2 sm:pt-3">
          <PriceTag price={product.price} compareAtPrice={product.compare_at_price} size="card" />
          <AddToCartButton product={product} className="relative z-10 -my-1.5 -mr-1.5 ml-auto sm:m-0 sm:ml-auto" />
        </div>
      </div>
    </article>
  );
}
