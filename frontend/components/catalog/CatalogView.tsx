import Link from 'next/link';
import type { ReactNode } from 'react';
import { ChevronDownIcon } from '@/components/ui/icons';
import { WHATSAPP_LINK_PROPS, whatsappHref } from '@/lib/config/contact';
import type { Brand, Category, PaginatedResponse, Product } from '@/lib/api/types';
import {
  activeFilterCount,
  catalogHref,
  DEFAULT_SORT,
  formTarget,
  type CatalogParams,
  type CatalogScope,
} from '@/lib/catalog/params';
import { formatNumber } from '@/lib/utils/format';
import { isRealBrand } from '@/lib/utils/product';
import { Breadcrumb, type BreadcrumbItem } from './Breadcrumb';
import { ActiveFilters } from './filters/ActiveFilters';
import { BrandFilter } from './filters/BrandFilter';
import { CategoryFilter } from './filters/CategoryFilter';
import { MobileFilters } from './filters/MobileFilters';
import { PriceStockForm } from './filters/PriceStockForm';
import { SortSelect } from './filters/SortSelect';
import { Pagination } from './Pagination';
import { ProductGrid } from './ProductGrid';

const ADVICE_MESSAGE = "Bonjour, j'aimerais un conseil pour choisir un produit.";

function FilterCard({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="flex flex-col gap-3 rounded-[18px] border border-bordure bg-blanc p-[22px]">
      <h2 className="text-[11.5px] font-semibold uppercase tracking-[0.18em] text-texte-discret">{title}</h2>
      {children}
    </section>
  );
}

/** Section repliable du panneau mobile ; le titre rappelle la valeur choisie. */
function MobileFilterSection({
  title,
  value,
  defaultOpen = false,
  children,
}: {
  title: string;
  value?: string;
  defaultOpen?: boolean;
  children: ReactNode;
}) {
  return (
    <details open={defaultOpen} className="group border-b border-bordure last:border-b-0">
      <summary className="flex min-h-[52px] cursor-pointer list-none items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
        <span className="flex min-w-0 flex-col">
          <span className="text-[11.5px] font-semibold uppercase tracking-[0.18em] text-texte-discret">{title}</span>
          {value && <span className="truncate text-sm font-semibold text-vert">{value}</span>}
        </span>
        <ChevronDownIcon className="h-5 w-5 shrink-0 text-encre transition-transform group-open:rotate-180" />
      </summary>
      <div className="pb-4">{children}</div>
    </details>
  );
}

function pluralProducts(total: number): string {
  return `${formatNumber(total)} produit${total > 1 ? 's' : ''}`;
}

/**
 * Gabarit commun des pages catalogue (DESIGN.md §3) : /produits,
 * /categories/[slug] et /marques/[slug]. Fil d'Ariane, titre et
 * description, colonne de filtres (catégorie, marque avec recherche, prix,
 * en stock), barre de tri avec compteur, grille 3 colonnes, pagination.
 * Mobile : filtres derrière un bouton avec compteur, filtres actifs en
 * pastilles, grille 2 colonnes.
 */
export function CatalogView({
  scope,
  params,
  title,
  description,
  breadcrumb,
  countContext,
  products,
  categories,
  brands,
}: {
  scope: CatalogScope;
  params: CatalogParams;
  title: string;
  description?: string | null;
  breadcrumb: BreadcrumbItem[];
  /** Suite du compteur : « dans cette catégorie », « pour « savon » »… */
  countContext: string;
  products: PaginatedResponse<Product>;
  categories: Category[];
  brands: Brand[];
}) {
  const total = products.meta.total;

  // Marques du périmètre (l'API ne renvoie que celles de la catégorie
  // choisie) ; sans produit, masquées — sauf la marque sélectionnée.
  const brandOptions = brands
    .filter((brand) => isRealBrand(brand) && (brand.products_count !== 0 || brand.slug === params.brand))
    .sort((a, b) => a.name.localeCompare(b.name, 'fr'))
    .map((brand) => ({
      slug: brand.slug,
      name: brand.name,
      href: catalogHref(scope, params, { brand: brand.slug }),
      active: params.brand === brand.slug,
      count: brand.products_count,
    }));
  const clearBrandHref = catalogHref(scope, params, { brand: null });
  const currentCategoryName = categories.find((category) => category.slug === params.category)?.name;
  const currentBrandName = brands.find((brand) => brand.slug === params.brand)?.name;

  const priceTarget = formTarget(catalogHref(scope, params, { price_min: null, price_max: null, in_stock: null }));
  const sortTarget = formTarget(catalogHref(scope, params, { sort: null }));
  // Remonte les formulaires quand l'URL change (ex. pastille supprimée) :
  // leurs champs sont non contrôlés.
  const priceKey = `${priceTarget.action}?${params.price_min ?? ''}-${params.price_max ?? ''}-${params.in_stock ?? ''}`;
  const sortKey = `${sortTarget.action}?${params.sort ?? ''}`;

  const paginationTarget = catalogHref(scope, params, { page: null });
  const [paginationPath, paginationQuery = ''] = paginationTarget.split('?');

  const categoryFilter = <CategoryFilter categories={categories} scope={scope} params={params} />;
  const brandFilter = <BrandFilter options={brandOptions} clearHref={clearBrandHref} />;

  const clearAllHref = catalogHref(scope, params, {
    q: null,
    category: scope.kind === 'category' ? undefined : null,
    brand: scope.kind === 'brand' ? undefined : null,
    price_min: null,
    price_max: null,
    in_stock: null,
    featured: null,
    tags: null,
  });
  const hasFilters = activeFilterCount(params, scope) > 0 || params.q !== undefined;

  return (
    <div className="mx-auto max-w-[1440px] px-4 pb-4 pt-[22px] sm:px-8 lg:px-16 lg:pt-10">
      <header className="flex flex-col gap-[9px] pb-4 lg:gap-3.5 lg:pb-[30px]">
        <Breadcrumb items={breadcrumb} />
        <h1 className="font-titre text-[32px] leading-tight text-vert lg:text-[44px]">{title}</h1>
        <p className="max-w-[640px] text-sm leading-relaxed text-texte-doux lg:text-[15.5px]">
          {description ? <>{description} </> : null}
          Un doute&nbsp;?{' '}
          <a
            href={whatsappHref(ADVICE_MESSAGE)}
            {...WHATSAPP_LINK_PROPS}
            className="font-semibold text-vert underline decoration-bordure-forte underline-offset-4 hover:decoration-vert"
          >
            Notre pharmacien vous oriente sur WhatsApp
          </a>
          .
        </p>
        <p className="text-sm text-texte-doux lg:hidden">
          <strong className="font-semibold text-encre">{pluralProducts(total)}</strong> {countContext}
        </p>
      </header>

      {/* Mobile : bouton Filtrer + tri. */}
      <div className="flex gap-2.5 pb-3.5 lg:hidden">
        <MobileFilters activeCount={activeFilterCount(params, scope)}>
          <MobileFilterSection title="Catégorie" value={currentCategoryName ?? 'Toutes'}>
            {categoryFilter}
          </MobileFilterSection>
          <MobileFilterSection title="Marque" value={currentBrandName ?? 'Toutes'}>
            {brandFilter}
          </MobileFilterSection>
          <MobileFilterSection title="Prix et disponibilité" defaultOpen>
            <PriceStockForm
              key={priceKey}
              action={priceTarget.action}
              hidden={priceTarget.hidden}
              priceMin={params.price_min}
              priceMax={params.price_max}
              inStock={params.in_stock === '1'}
              autoSubmit={false}
              submitLabel="Afficher les produits"
              showLegend={false}
            />
          </MobileFilterSection>
        </MobileFilters>
        <SortSelect
          key={`m-${sortKey}`}
          action={sortTarget.action}
          hidden={sortTarget.hidden}
          value={params.sort ?? DEFAULT_SORT}
          showLabel={false}
          className="flex-1"
        />
      </div>

      <div className="lg:flex lg:gap-10">
        <aside aria-label="Filtres" className="hidden lg:flex lg:w-[268px] lg:shrink-0 lg:flex-col lg:gap-[22px]">
          <FilterCard title="Catégorie">{categoryFilter}</FilterCard>
          <FilterCard title="Marque">{brandFilter}</FilterCard>
          <section className="rounded-[18px] border border-bordure bg-blanc p-[22px]">
            <h2 className="sr-only">Prix et disponibilité</h2>
            <PriceStockForm
              key={priceKey}
              action={priceTarget.action}
              hidden={priceTarget.hidden}
              priceMin={params.price_min}
              priceMax={params.price_max}
              inStock={params.in_stock === '1'}
              autoSubmit
              submitLabel="Appliquer le prix"
            />
          </section>
        </aside>

        <div className="min-w-0 flex-1">
          <div className="hidden min-h-[52px] items-center justify-between gap-6 pb-[22px] lg:flex">
            <p className="text-[14.5px] text-texte-doux" aria-live="polite">
              <strong className="font-semibold text-encre">{pluralProducts(total)}</strong> {countContext}
            </p>
            <SortSelect key={`d-${sortKey}`} action={sortTarget.action} hidden={sortTarget.hidden} value={params.sort ?? DEFAULT_SORT} showLabel />
          </div>

          <div className="pb-4 empty:hidden lg:pb-[22px]">
            <ActiveFilters scope={scope} params={params} categories={categories} brands={brands} />
          </div>

          <ProductGrid
            layout="catalog"
            products={products.data}
            emptyTitle={hasFilters ? 'Aucun produit ne correspond à ces filtres' : 'Aucun produit pour le moment'}
            emptyDescription={
              hasFilters
                ? 'Essayez d’élargir la fourchette de prix ou de retirer un filtre.'
                : 'Revenez bientôt, le catalogue est mis à jour régulièrement.'
            }
            emptyAction={
              hasFilters ? (
                <Link
                  href={clearAllHref}
                  className="mt-2 flex h-11 items-center rounded-full border border-bordure-forte px-5 text-sm font-semibold text-vert hover:border-vert"
                >
                  Effacer les filtres
                </Link>
              ) : undefined
            }
          />

          <Pagination
            meta={products.meta}
            basePath={paginationPath}
            searchParams={Object.fromEntries(new URLSearchParams(paginationQuery))}
          />
        </div>
      </div>
    </div>
  );
}
