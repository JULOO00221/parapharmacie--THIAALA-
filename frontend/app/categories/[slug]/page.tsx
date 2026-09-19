import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { cache } from 'react';
import { ProductGrid } from '@/components/catalog/ProductGrid';
import { Pagination } from '@/components/catalog/Pagination';
import { getCategory } from '@/lib/api/categories';
import { getProducts } from '@/lib/api/products';

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
    description: category.description ?? `Découvrez nos produits ${category.name.toLowerCase()} chez Parapharmacie THIAALA.`,
    alternates: { canonical: `/categories/${category.slug}` },
    openGraph: {
      title: category.name,
      description: category.description ?? undefined,
      images: category.image_url ? [category.image_url] : undefined,
    },
  };
}

export default async function CategoryPage({ params, searchParams }: PageProps<'/categories/[slug]'>) {
  const { slug } = await params;
  const query = await searchParams;
  const page = typeof query.page === 'string' ? query.page : '1';

  const category = await loadCategory(slug);
  const products = await getProducts({ category: slug, page: Number(page) });

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
      <header className="mb-8">
        {category.parent && <p className="text-sm text-ink-muted">{category.parent.name}</p>}
        <h1 className="text-2xl font-bold text-ink sm:text-3xl">{category.name}</h1>
        {category.description && <p className="mt-2 max-w-2xl text-ink-muted">{category.description}</p>}
        <p className="mt-2 text-sm text-ink-muted">{products.meta.total} produit{products.meta.total > 1 ? 's' : ''}</p>
      </header>

      <ProductGrid
        products={products.data}
        emptyTitle="Aucun produit dans cette catégorie pour le moment"
        emptyDescription="Revenez bientôt, le catalogue est mis à jour régulièrement."
      />

      <Pagination meta={products.meta} basePath={`/categories/${slug}`} searchParams={{ page: page !== '1' ? page : undefined }} />
    </div>
  );
}
