import type { Metadata } from 'next';
import Image from 'next/image';
import { notFound } from 'next/navigation';
import { ProductGrid } from '@/components/catalog/ProductGrid';
import { Pagination } from '@/components/catalog/Pagination';
import { ApiError } from '@/lib/api/client';
import { getBrand } from '@/lib/api/brands';
import { getProducts } from '@/lib/api/products';

export const dynamic = 'force-dynamic';

async function loadBrand(slug: string) {
  try {
    return await getBrand(slug);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      notFound();
    }
    throw error;
  }
}

export async function generateMetadata({ params }: PageProps<'/marques/[slug]'>): Promise<Metadata> {
  const { slug } = await params;
  const brand = await loadBrand(slug);

  return {
    title: brand.name,
    description: brand.description ?? `Produits de la marque ${brand.name} chez Parapharmacie THIAALA.`,
    alternates: { canonical: `/marques/${brand.slug}` },
    openGraph: {
      title: brand.name,
      description: brand.description ?? undefined,
      images: brand.logo_url ? [brand.logo_url] : undefined,
    },
  };
}

export default async function BrandPage({ params, searchParams }: PageProps<'/marques/[slug]'>) {
  const { slug } = await params;
  const query = await searchParams;
  const page = typeof query.page === 'string' ? query.page : '1';

  const brand = await loadBrand(slug);
  const products = await getProducts({ brand: slug, page: Number(page) });

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
      <header className="mb-8 flex items-center gap-4">
        {brand.logo_url && (
          <Image src={brand.logo_url} alt={brand.name} width={64} height={64} className="rounded-full border border-border" />
        )}
        <div>
          <h1 className="text-2xl font-bold text-ink sm:text-3xl">{brand.name}</h1>
          <p className="text-sm text-ink-muted">{products.meta.total} produit{products.meta.total > 1 ? 's' : ''}</p>
        </div>
      </header>

      {brand.description && <p className="mb-8 max-w-2xl text-ink-muted">{brand.description}</p>}

      <ProductGrid
        products={products.data}
        emptyTitle="Aucun produit de cette marque pour le moment"
        emptyDescription="Revenez bientôt, le catalogue est mis à jour régulièrement."
      />

      <Pagination meta={products.meta} basePath={`/marques/${slug}`} searchParams={{ page: page !== '1' ? page : undefined }} />
    </div>
  );
}
