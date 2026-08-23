import type { MetadataRoute } from 'next';
import { getBrands } from '@/lib/api/brands';
import { getCategories } from '@/lib/api/categories';
import { getProducts } from '@/lib/api/products';

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL ?? 'http://localhost:3000';

// Generated at request time, not build time — the sitemap depends on the
// Laravel API being reachable, which shouldn't be a hard build-time
// dependency (the API and the frontend are deployed separately).
export const dynamic = 'force-dynamic';

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const [categories, brands] = await Promise.all([getCategories(), getBrands()]);

  // The catalogue is small enough (24 products today) to fetch a single
  // large page rather than paginate through generateSitemaps().
  const products = await getProducts({ per_page: 100 });

  return [
    { url: siteUrl, changeFrequency: 'daily', priority: 1 },
    { url: `${siteUrl}/produits`, changeFrequency: 'daily', priority: 0.9 },
    ...categories.map((category) => ({
      url: `${siteUrl}/categories/${category.slug}`,
      changeFrequency: 'weekly' as const,
      priority: 0.6,
    })),
    ...brands.map((brand) => ({
      url: `${siteUrl}/marques/${brand.slug}`,
      changeFrequency: 'weekly' as const,
      priority: 0.5,
    })),
    ...products.data.map((product) => ({
      url: `${siteUrl}/produits/${product.slug}`,
      changeFrequency: 'weekly' as const,
      priority: 0.7,
    })),
  ];
}
