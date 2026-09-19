import type { ProductFilters, ProductSort } from '@/lib/api/products';

/**
 * URL state of the catalogue pages (/produits, /categories/[slug],
 * /marques/[slug]). Pure functions only: parsing, conversion to API
 * filters, and link building. No business rule lives here — the API
 * applies the filters.
 */

export const SORT_OPTIONS: Array<{ value: ProductSort; label: string }> = [
  { value: 'newest', label: 'Nouveautés' },
  { value: 'price_asc', label: 'Prix croissant' },
  { value: 'price_desc', label: 'Prix décroissant' },
  { value: 'name_asc', label: 'Nom A → Z' },
  { value: 'name_desc', label: 'Nom Z → A' },
  { value: 'featured_first', label: 'Mis en avant d’abord' },
];

export const DEFAULT_SORT: ProductSort = 'newest';

/** Divisible by 2 and 3: full rows on mobile (2 columns) and desktop (3). */
export const CATALOG_PER_PAGE = 24;

export interface CatalogParams {
  q?: string;
  category?: string;
  brand?: string;
  tags?: string;
  price_min?: string;
  price_max?: string;
  in_stock?: '1';
  featured?: '1';
  sort?: ProductSort;
  page?: string;
}

/** Which route the page is: the route itself may fix the category or the brand. */
export type CatalogScope =
  | { kind: 'all' }
  | { kind: 'category'; slug: string }
  | { kind: 'brand'; slug: string };

type RawQuery = Record<string, string | string[] | undefined>;

function first(value: string | string[] | undefined): string | undefined {
  const single = Array.isArray(value) ? value[0] : value;
  const trimmed = single?.trim();

  return trimmed ? trimmed : undefined;
}

function wholeNumber(value: string | undefined, min: number): string | undefined {
  if (value === undefined || !/^\d+$/.test(value)) return undefined;

  return Number(value) >= min ? String(Number(value)) : undefined;
}

/**
 * Reads the query string of a catalogue page. The route's own slug wins
 * over a query parameter of the same kind (/categories/a?category=b is a).
 * Invalid values are dropped rather than forwarded to the API.
 */
export function parseCatalogParams(query: RawQuery, scope: CatalogScope): CatalogParams {
  const sort = first(query.sort);

  return {
    q: first(query.q),
    category: scope.kind === 'category' ? scope.slug : first(query.category),
    brand: scope.kind === 'brand' ? scope.slug : first(query.brand),
    tags: first(query.tags),
    price_min: wholeNumber(first(query.price_min), 0),
    price_max: wholeNumber(first(query.price_max), 0),
    in_stock: first(query.in_stock) === '1' ? '1' : undefined,
    featured: first(query.featured) === '1' ? '1' : undefined,
    sort: SORT_OPTIONS.some((option) => option.value === sort) ? (sort as ProductSort) : undefined,
    page: wholeNumber(first(query.page), 2),
  };
}

export function toProductFilters(params: CatalogParams, perPage: number): ProductFilters {
  return {
    q: params.q,
    category: params.category,
    brand: params.brand,
    tags: params.tags,
    price_min: params.price_min !== undefined ? Number(params.price_min) : undefined,
    price_max: params.price_max !== undefined ? Number(params.price_max) : undefined,
    in_stock: params.in_stock === '1' ? true : undefined,
    featured: params.featured === '1' ? true : undefined,
    sort: params.sort,
    page: params.page !== undefined ? Number(params.page) : undefined,
    per_page: perPage,
  };
}

const QUERY_ORDER: Array<keyof CatalogParams> = [
  'q',
  'category',
  'brand',
  'tags',
  'price_min',
  'price_max',
  'in_stock',
  'featured',
  'sort',
  'page',
];

/**
 * Link to the catalogue with `changes` applied (`null` removes a param).
 * Any change other than `page` resets pagination.
 *
 * Path choice: a brand page keeps its brand in the path as long as a brand
 * is selected; otherwise a selected category lives in the path
 * (/categories/slug, the canonical category URL); otherwise /produits.
 * Every other filter goes to the query string.
 */
export function catalogHref(
  scope: CatalogScope,
  params: CatalogParams,
  changes: { [K in keyof CatalogParams]?: CatalogParams[K] | null } = {},
): string {
  const next: CatalogParams = { ...params };

  for (const [key, value] of Object.entries(changes) as Array<[keyof CatalogParams, string | null | undefined]>) {
    if (value === undefined) continue;
    if (value === null) {
      delete next[key];
    } else {
      (next as Record<string, string>)[key] = value;
    }
  }

  const onlyPageChanged = Object.keys(changes).every((key) => key === 'page');
  if (!onlyPageChanged) delete next.page;
  if (next.sort === DEFAULT_SORT) delete next.sort;

  let path = '/produits';
  const pathConsumes = new Set<keyof CatalogParams>();

  if (scope.kind === 'brand' && next.brand) {
    path = `/marques/${encodeURIComponent(next.brand)}`;
    pathConsumes.add('brand');
  } else if (next.category) {
    path = `/categories/${encodeURIComponent(next.category)}`;
    pathConsumes.add('category');
  }

  const search = new URLSearchParams();
  for (const key of QUERY_ORDER) {
    const value = next[key];
    if (value !== undefined && !pathConsumes.has(key)) search.set(key, value);
  }

  const query = search.toString();

  return query ? `${path}?${query}` : path;
}

/** Filters that narrow the results (search and sort excluded) — the mobile « Filtrer » badge. */
export function activeFilterCount(params: CatalogParams, scope: CatalogScope): number {
  let count = 0;

  if (params.category && scope.kind !== 'category') count += 1;
  if (params.brand && scope.kind !== 'brand') count += 1;
  if (params.tags) count += 1;
  if (params.price_min !== undefined || params.price_max !== undefined) count += 1;
  if (params.in_stock) count += 1;
  if (params.featured) count += 1;

  return count;
}

/**
 * A GET <form> replaces the whole query string on submit, so the other
 * active filters must travel as hidden inputs. Splits a catalogue link
 * into the form's action and those hidden fields.
 */
export function formTarget(href: string): { action: string; hidden: Array<[string, string]> } {
  const [action, query = ''] = href.split('?');

  return { action, hidden: [...new URLSearchParams(query).entries()] };
}
