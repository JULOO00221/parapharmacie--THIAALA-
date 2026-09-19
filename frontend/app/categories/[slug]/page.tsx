import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { cache } from 'react';
import { CatalogView } from '@/components/catalog/CatalogView';
import type { BreadcrumbItem } from '@/components/catalog/Breadcrumb';
import { getBrands } from '@/lib/api/brands';
import { getCategories, getCategory } from '@/lib/api/categories';
import { getProducts } from '@/lib/api/products';
import { CATALOG_PER_PAGE, parseCatalogParams, toProductFilters } from '@/lib/catalog/params';

export const dynamic = 'force-dynamic';

// cache() : generateMetadata et la page partagent un seul appel API par
// requête. Next ne déduplique pas ce fetch tout seul, car request() lui
// passe toujours un AbortSignal (timeout), ce qui désactive la
// mémoïsation automatique des fetch.
// getCategory() ne lève jamais : null = introuvable (404) ou API indisponible.
const loadCategory = cache(async (slug: string) => {
  const category = await getCategory(slug);

  if (category === null) {
    notFound();
  }

  return category;
});

export async function generateMetadata({ params }: PageProps<'/categories/[slug]'>): Promise<Metadata> {
  const { slug } = await params;
  const category = await loadCategory(slug);

  return {
    title: category.name,
    description:
      category.description ??
      `${category.name} : découvrez nos produits à la Parapharmacie THIAALA, livrés à Tambacounda et dans la région.`,
    alternates: { canonical: `/categories/${category.slug}` },
    openGraph: {
      title: category.name,
      description: category.description ?? undefined,
      images: category.image_url ? [category.image_url] : undefined,
    },
  };
}

export default async function CategoryPage({ params: routeParams, searchParams }: PageProps<'/categories/[slug]'>) {
  const { slug } = await routeParams;
  const scope = { kind: 'category', slug } as const;
  const params = parseCatalogParams(await searchParams, scope);

  const [category, products, categories, brands] = await Promise.all([
    loadCategory(slug),
    getProducts(toProductFilters(params, CATALOG_PER_PAGE)),
    getCategories({ brand: params.brand }),
    // Seulement les marques qui ont des produits dans ce rayon.
    getBrands({ category: slug }),
  ]);

  const breadcrumb: BreadcrumbItem[] = [
    { label: 'Accueil', href: '/' },
    { label: 'Tous les produits', href: '/produits' },
    ...(category.parent ? [{ label: category.parent.name, href: `/categories/${category.parent.slug}` }] : []),
    { label: category.name },
  ];

  return (
    <CatalogView
      scope={scope}
      params={params}
      title={category.name}
      description={category.description}
      breadcrumb={breadcrumb}
      countContext={params.q ? `pour « ${params.q} » dans cette catégorie` : 'dans cette catégorie'}
      products={products}
      categories={categories}
      brands={brands}
    />
  );
}
