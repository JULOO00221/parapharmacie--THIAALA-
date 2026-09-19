import Link from 'next/link';
import { CloseIcon } from '@/components/ui/icons';
import type { Brand, Category } from '@/lib/api/types';
import { catalogHref, type CatalogParams, type CatalogScope } from '@/lib/catalog/params';
import { formatPrice } from '@/lib/utils/format';

interface Pill {
  key: string;
  label: string;
  href: string;
}

function priceLabel(min?: string, max?: string): string {
  if (min !== undefined && max !== undefined) return `${formatPrice(min)} – ${formatPrice(max)}`;
  if (min !== undefined) return `Dès ${formatPrice(min)}`;
  return `Jusqu’à ${formatPrice(max ?? '0')}`;
}

/**
 * Filtres actifs en pastilles supprimables (DESIGN.md : mobile ; affichées
 * aussi sur desktop, où elles restent utiles). Chaque pastille est un lien
 * vers la même page sans ce filtre. Le filtre porté par la route elle-même
 * (la catégorie d'une page catégorie, la marque d'une page marque) n'en
 * fait pas partie : c'est la page, pas un filtre.
 */
export function ActiveFilters({
  scope,
  params,
  categories,
  brands,
}: {
  scope: CatalogScope;
  params: CatalogParams;
  categories: Category[];
  brands: Brand[];
}) {
  const pills: Pill[] = [];

  if (params.q) {
    pills.push({ key: 'q', label: `« ${params.q} »`, href: catalogHref(scope, params, { q: null }) });
  }
  if (params.category && scope.kind !== 'category') {
    const name = categories.find((category) => category.slug === params.category)?.name ?? params.category;
    pills.push({ key: 'category', label: name, href: catalogHref(scope, params, { category: null }) });
  }
  if (params.brand && scope.kind !== 'brand') {
    const name = brands.find((brand) => brand.slug === params.brand)?.name ?? params.brand;
    pills.push({ key: 'brand', label: name, href: catalogHref(scope, params, { brand: null }) });
  }
  if (params.price_min !== undefined || params.price_max !== undefined) {
    pills.push({
      key: 'price',
      label: priceLabel(params.price_min, params.price_max),
      href: catalogHref(scope, params, { price_min: null, price_max: null }),
    });
  }
  if (params.in_stock) {
    pills.push({ key: 'in_stock', label: 'En stock', href: catalogHref(scope, params, { in_stock: null }) });
  }
  if (params.featured) {
    pills.push({ key: 'featured', label: 'Mis en avant', href: catalogHref(scope, params, { featured: null }) });
  }
  if (params.tags) {
    pills.push({ key: 'tags', label: `Tag : ${params.tags}`, href: catalogHref(scope, params, { tags: null }) });
  }

  if (pills.length === 0) return null;

  const clearAll = catalogHref(scope, params, {
    q: null,
    category: scope.kind === 'category' ? undefined : null,
    brand: scope.kind === 'brand' ? undefined : null,
    price_min: null,
    price_max: null,
    in_stock: null,
    featured: null,
    tags: null,
  });

  return (
    <div className="flex flex-wrap items-center gap-2">
      <p className="sr-only">Filtres actifs :</p>
      <ul className="contents">
        {pills.map((pill) => (
          <li key={pill.key}>
            <Link
              href={pill.href}
              scroll={false}
              aria-label={`Retirer le filtre ${pill.label}`}
              className="flex h-11 items-center gap-[7px] rounded-full bg-vert/[0.09] px-[13px] text-[13px] font-medium text-vert transition-colors hover:bg-vert/15"
            >
              {pill.label}
              <CloseIcon className="h-3 w-3" strokeWidth={2.4} />
            </Link>
          </li>
        ))}
      </ul>
      {pills.length > 1 && (
        <Link
          href={clearAll}
          scroll={false}
          className="flex h-11 items-center px-2 text-[13px] font-semibold text-texte-discret underline-offset-4 hover:text-vert hover:underline"
        >
          Tout effacer
        </Link>
      )}
    </div>
  );
}
