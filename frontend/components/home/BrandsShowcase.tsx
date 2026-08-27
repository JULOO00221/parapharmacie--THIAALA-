import Link from 'next/link';
import type { Brand } from '@/lib/api/types';

/**
 * No brand has a logo_url in the current catalogue (verified against the
 * live API) — a plain pill list of real brand names/links, no invented
 * logos or imagery.
 */
export function BrandsShowcase({ brands }: { brands: Brand[] }) {
  if (brands.length === 0) return null;

  return (
    <section className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
      <h2 className="font-display text-xl font-semibold text-ink sm:text-2xl">Nos marques</h2>
      <div className="mt-6 flex flex-wrap gap-3">
        {brands.map((brand) => (
          <Link
            key={brand.id}
            href={`/marques/${brand.slug}`}
            className="rounded-full border border-border bg-surface-raised px-5 py-2.5 text-sm font-medium text-ink transition-colors hover:border-brand-400 hover:bg-brand-50"
          >
            {brand.name}
          </Link>
        ))}
      </div>
    </section>
  );
}
