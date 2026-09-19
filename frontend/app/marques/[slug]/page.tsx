import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { cache } from 'react';
import { CatalogView } from '@/components/catalog/CatalogView';
import { getBrand, getBrands } from '@/lib/api/brands';
import { getCategories } from '@/lib/api/categories';
import { getProducts } from '@/lib/api/products';
import { CATALOG_PER_PAGE, parseCatalogParams, toProductFilters } from '@/lib/catalog/params';

export const dynamic = 'force-dynamic';

// cache() : generateMetadata et la page partagent un seul appel API par
// requête. Next ne déduplique pas ce fetch tout seul, car request() lui
// passe toujours un AbortSignal (timeout), ce qui désactive la
// mémoïsation automatique des fetch.
// getBrand() ne lève jamais : null = introuvable (404) ou API indisponible.
const loadBrand = cache(async (slug: string) => {
  const brand = await getBrand(slug);

  if (brand === null) {
    notFound();
  }

  return brand;
});

export async function generateMetadata({ params }: PageProps<'/marques/[slug]'>): Promise<Metadata> {
  const { slug } = await params;
  const brand = await loadBrand(slug);

  return {
    title: brand.name,
    description:
      brand.description ??
      `Produits ${brand.name} à la Parapharmacie THIAALA, livrés à Tambacounda et dans la région.`,
    alternates: { canonical: `/marques/${brand.slug}` },
    openGraph: {
      title: brand.name,
      description: brand.description ?? undefined,
      images: brand.logo_url ? [brand.logo_url] : undefined,
    },
  };
}

export default async function BrandPage({ params: routeParams, searchParams }: PageProps<'/marques/[slug]'>) {
  const { slug } = await routeParams;
  const scope = { kind: 'brand', slug } as const;
  const params = parseCatalogParams(await searchParams, scope);

  const [brand, products, categories, brands] = await Promise.all([
    loadBrand(slug),
    getProducts(toProductFilters(params, CATALOG_PER_PAGE)),
    // Catégories comptées pour cette marque seulement.
    getCategories({ brand: slug }),
    getBrands({ category: params.category }),
  ]);

  return (
    <CatalogView
      scope={scope}
      params={params}
      title={brand.name}
      description={brand.description}
      breadcrumb={[{ label: 'Accueil', href: '/' }, { label: 'Tous les produits', href: '/produits' }, { label: brand.name }]}
      countContext={params.q ? `pour « ${params.q} » de cette marque` : 'de cette marque'}
      products={products}
      categories={categories}
      brands={brands}
    />
  );
}
