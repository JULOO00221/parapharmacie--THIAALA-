import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { getBrand, getBrands } from './brands';
import { getCategories, getCategory } from './categories';
import { CATALOG_CACHE_TAG, CATALOG_REVALIDATE_SECONDS } from './client';
import { getProduct, getProducts } from './products';
import { getTags } from './tags';

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

describe('lib/api catalogue reads', () => {
  beforeEach(() => {
    vi.stubEnv('NEXT_PUBLIC_API_URL', 'http://api.test/api/v1');
    vi.spyOn(console, 'error').mockImplementation(() => {});
  });

  afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllEnvs();
  });

  it('caches every catalogue read for 300 seconds under the catalog tag', async () => {
    const fetchSpy = vi.spyOn(global, 'fetch').mockImplementation(async () => jsonResponse({ data: [] }));

    await Promise.all([getProducts(), getProduct('x'), getCategories(), getCategory('x'), getBrands(), getBrand('x'), getTags()]);

    expect(fetchSpy).toHaveBeenCalledTimes(7);
    for (const [, init] of fetchSpy.mock.calls) {
      expect((init as RequestInit & { next?: unknown }).next).toEqual({
        revalidate: CATALOG_REVALIDATE_SECONDS,
        tags: [CATALOG_CACHE_TAG],
      });
      expect(init?.cache).toBeUndefined();
    }
  });

  it('scopes brand and category lists through query parameters', async () => {
    const fetchSpy = vi.spyOn(global, 'fetch').mockImplementation(async () => jsonResponse({ data: [] }));

    await getBrands({ category: 'soins-du-visage' });
    await getCategories({ brand: 'avene' });
    await getBrands();

    const urls = fetchSpy.mock.calls.map(([url]) => String(url));
    expect(urls[0]).toMatch(/\/brands\?category=soins-du-visage$/);
    expect(urls[1]).toMatch(/\/categories\?brand=avene$/);
    expect(urls[2]).toMatch(/\/brands$/);
  });

  it('drops revalidate when the caller forces an explicit cache mode', async () => {
    const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: [] }));

    await getBrands({ cache: 'no-store' });

    const init = fetchSpy.mock.calls[0][1] as RequestInit & { next?: unknown };
    expect(init.cache).toBe('no-store');
    expect(init.next).toBeUndefined();
  });

  it.each([
    ['a 500', () => jsonResponse({ message: 'Server Error' }, 500)],
    ['a 429', () => jsonResponse({ message: 'Too Many Attempts.' }, 429)],
    ['a network failure', () => { throw new TypeError('fetch failed'); }],
  ])('returns default values instead of throwing on %s', async (_label, respond) => {
    vi.spyOn(global, 'fetch').mockImplementation(async () => respond());

    const products = await getProducts({ per_page: 8 });
    expect(products.data).toEqual([]);
    expect(products.meta.total).toBe(0);
    expect(products.meta.last_page).toBe(1);

    await expect(getProduct('x')).resolves.toBeNull();
    await expect(getCategory('x')).resolves.toBeNull();
    await expect(getBrand('x')).resolves.toBeNull();
    await expect(getCategories()).resolves.toEqual([]);
    await expect(getBrands()).resolves.toEqual([]);
    await expect(getTags()).resolves.toEqual([]);
    expect(console.error).toHaveBeenCalled();
  });

  it('returns null for an unknown slug without logging an error', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ message: 'Not Found' }, 404));

    await expect(getProduct('inconnu')).resolves.toBeNull();
    expect(console.error).not.toHaveBeenCalled();
  });

  it('still returns the real data on success', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: { id: 1, slug: 'savon' } }));

    await expect(getProduct('savon')).resolves.toEqual({ id: 1, slug: 'savon' });
  });
});
