import Link from 'next/link';
import type { Brand } from '@/lib/api/types';
import { isRealBrand } from '@/lib/utils/product';

const MAX_BRANDS = 9;

/**
 * Marques mises en avant dans le bandeau, par ordre de préférence. Seules
 * celles qui existent réellement dans l'API s'affichent ; le bandeau se
 * complète avec les autres marques du catalogue.
 */
const PREFERRED = ['Avène', 'Bioderma', 'La Roche-Posay', 'Topicrem', 'Uriage', 'CeraVe', 'SVR', 'Nivea', 'Klorane'];

function pickBrands(brands: Brand[]): Brand[] {
  const real = brands.filter(isRealBrand);
  const byName = new Map(real.map((brand) => [brand.name.toLowerCase(), brand]));
  const preferred = PREFERRED.flatMap((name) => byName.get(name.toLowerCase()) ?? []);
  const others = real.filter((brand) => !preferred.includes(brand));

  return [...preferred, ...others].slice(0, MAX_BRANDS);
}

/**
 * Bandeau de marques (DESIGN.md §3) : noms en Fraunces, sans logos — aucune
 * marque n'a de logo_url dans le catalogue actuel. Chaque nom mène à la
 * page de la marque.
 */
export function BrandsShowcase({ brands }: { brands: Brand[] }) {
  const shown = pickBrands(brands);

  if (shown.length === 0) return null;

  return (
    <section aria-labelledby="marques-title" className="mx-auto max-w-[1440px] px-5 pt-[34px] sm:px-8 lg:px-16 lg:pt-[60px]">
      <h2
        id="marques-title"
        className="surtitre text-center text-[10.5px] tracking-[0.2em] text-texte-discret lg:text-[12.5px] lg:tracking-[0.22em]"
      >
        Les marques que vous connaissez
      </h2>
      <ul className="mt-4 flex flex-wrap items-center justify-center gap-x-6 gap-y-1 lg:mt-[22px] lg:justify-between lg:gap-x-8">
        {shown.map((brand) => (
          <li key={brand.id}>
            <Link
              href={`/marques/${brand.slug}`}
              className="flex h-11 items-center font-display text-lg tracking-[0.02em] text-texte-doux transition-colors hover:text-vert lg:text-[23px]"
            >
              {brand.name}
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}
