import { describe, expect, it } from 'vitest';
import { activeFilterCount, catalogHref, formTarget, parseCatalogParams, toProductFilters, type CatalogScope } from './params';

const ALL: CatalogScope = { kind: 'all' };

describe('parseCatalogParams', () => {
  it('keeps valid values and drops invalid ones', () => {
    expect(
      parseCatalogParams(
        { q: ' savon ', price_min: '1000', price_max: 'abc', in_stock: '1', featured: 'yes', sort: 'price_asc', page: '3' },
        ALL,
      ),
    ).toEqual({
      q: 'savon',
      category: undefined,
      brand: undefined,
      tags: undefined,
      price_min: '1000',
      price_max: undefined,
      in_stock: '1',
      featured: undefined,
      sort: 'price_asc',
      page: '3',
    });
  });

  it('ignores an unknown sort and a page below 2', () => {
    const params = parseCatalogParams({ sort: 'random', page: '1' }, ALL);

    expect(params.sort).toBeUndefined();
    expect(params.page).toBeUndefined();
  });

  it('lets the route slug win over the query string', () => {
    expect(parseCatalogParams({ category: 'other' }, { kind: 'category', slug: 'soins-du-visage' }).category).toBe(
      'soins-du-visage',
    );
    expect(parseCatalogParams({ brand: 'other' }, { kind: 'brand', slug: 'bioderma' }).brand).toBe('bioderma');
  });

  it('takes the first value of a repeated parameter', () => {
    expect(parseCatalogParams({ q: ['a', 'b'] }, ALL).q).toBe('a');
  });
});

describe('toProductFilters', () => {
  it('converts URL strings to the API filter types', () => {
    expect(
      toProductFilters({ price_min: '500', price_max: '9000', in_stock: '1', page: '2', sort: 'newest' }, 24),
    ).toMatchObject({ price_min: 500, price_max: 9000, in_stock: true, page: 2, sort: 'newest', per_page: 24 });
  });
});

describe('catalogHref', () => {
  it('puts a selected category in the path', () => {
    expect(catalogHref(ALL, { q: 'creme' }, { category: 'soins-du-visage' })).toBe('/categories/soins-du-visage?q=creme');
  });

  it('keeps the brand page in its path and filters it by category in the query', () => {
    expect(catalogHref({ kind: 'brand', slug: 'bioderma' }, { brand: 'bioderma' }, { category: 'solaires' })).toBe(
      '/marques/bioderma?category=solaires',
    );
  });

  it('falls back to the category path when the brand is removed from a brand page', () => {
    expect(
      catalogHref({ kind: 'brand', slug: 'bioderma' }, { brand: 'bioderma', category: 'solaires' }, { brand: null }),
    ).toBe('/categories/solaires');
  });

  it('adds the brand as a query parameter on a category page', () => {
    expect(catalogHref({ kind: 'category', slug: 'solaires' }, { category: 'solaires' }, { brand: 'uriage' })).toBe(
      '/categories/solaires?brand=uriage',
    );
  });

  it('goes back to /produits when the category is removed', () => {
    expect(
      catalogHref({ kind: 'category', slug: 'solaires' }, { category: 'solaires', in_stock: '1' }, { category: null }),
    ).toBe('/produits?in_stock=1');
  });

  it('resets pagination on any filter change but keeps it for a page change', () => {
    expect(catalogHref(ALL, { page: '4', in_stock: '1' }, { in_stock: null })).toBe('/produits');
    expect(catalogHref(ALL, { page: '4', in_stock: '1' }, { page: '5' })).toBe('/produits?in_stock=1&page=5');
  });

  it('omits the default sort', () => {
    expect(catalogHref(ALL, {}, { sort: 'newest' })).toBe('/produits');
    expect(catalogHref(ALL, {}, { sort: 'price_asc' })).toBe('/produits?sort=price_asc');
  });

  it('encodes values', () => {
    expect(catalogHref(ALL, {}, { q: 'crème & gel' })).toBe('/produits?q=cr%C3%A8me+%26+gel');
  });
});

describe('activeFilterCount', () => {
  it('counts narrowing filters, not search or sort, and not the route’s own slug', () => {
    const scope: CatalogScope = { kind: 'category', slug: 'solaires' };

    expect(
      activeFilterCount(
        { q: 'x', sort: 'price_asc', category: 'solaires', brand: 'uriage', price_min: '100', price_max: '900', in_stock: '1' },
        scope,
      ),
    ).toBe(3);
  });
});

describe('formTarget', () => {
  it('splits a link into a form action and hidden fields', () => {
    expect(formTarget('/categories/solaires?brand=uriage&q=spf')).toEqual({
      action: '/categories/solaires',
      hidden: [
        ['brand', 'uriage'],
        ['q', 'spf'],
      ],
    });
    expect(formTarget('/produits')).toEqual({ action: '/produits', hidden: [] });
  });
});
