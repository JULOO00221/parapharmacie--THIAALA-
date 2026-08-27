import Link from 'next/link';
import type { Product } from '@/lib/api/types';
import { ProductGrid } from './ProductGrid';

/**
 * Shared shape for the homepage's product-driven sections (Mis en avant /
 * Promotions / Nouveautés) — each hides itself entirely rather than
 * rendering an empty heading over nothing, so a section only ever appears
 * when it has real products to show.
 */
export function ProductSection({
  id,
  title,
  description,
  viewAllHref,
  products,
}: {
  id?: string;
  title: string;
  description?: string;
  viewAllHref?: string;
  products: Product[];
}) {
  if (products.length === 0) return null;

  return (
    <section id={id} className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
      <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
          <h2 className="font-display text-xl font-semibold text-ink sm:text-2xl">{title}</h2>
          {description && <p className="mt-1 text-sm text-ink-muted">{description}</p>}
        </div>
        {viewAllHref && (
          <Link href={viewAllHref} className="shrink-0 text-sm font-medium text-brand-700 hover:underline">
            Tout voir
          </Link>
        )}
      </div>

      <ProductGrid products={products} />
    </section>
  );
}
