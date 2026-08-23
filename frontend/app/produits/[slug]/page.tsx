import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { Badge } from '@/components/ui/Badge';
import { PriceTag } from '@/components/product/PriceTag';
import { ProductGallery } from '@/components/product/ProductGallery';
import { StockBadge } from '@/components/product/StockBadge';
import { ApiError } from '@/lib/api/client';
import { getProduct } from '@/lib/api/products';

export const dynamic = 'force-dynamic';

async function loadProduct(slug: string) {
  try {
    return await getProduct(slug);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      notFound();
    }
    throw error;
  }
}

export async function generateMetadata({ params }: PageProps<'/produits/[slug]'>): Promise<Metadata> {
  const { slug } = await params;
  const product = await loadProduct(slug);

  return {
    title: product.name,
    description: product.short_description ?? product.description ?? `${product.name} — Tambacounda Cosmetix`,
    alternates: { canonical: `/produits/${product.slug}` },
    openGraph: {
      title: product.name,
      description: product.short_description ?? undefined,
      images: product.primary_image ? [product.primary_image.url] : undefined,
      type: 'website',
    },
  };
}

export default async function ProductPage({ params }: PageProps<'/produits/[slug]'>) {
  const { slug } = await params;
  const product = await loadProduct(slug);

  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: product.name,
    description: product.short_description ?? product.description ?? undefined,
    sku: product.sku,
    image: product.images.map((image) => image.url),
    brand: product.brand ? { '@type': 'Brand', name: product.brand.name } : undefined,
    offers: {
      '@type': 'Offer',
      priceCurrency: 'XOF',
      price: product.price,
      availability: product.available ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
      url: `/produits/${product.slug}`,
    },
  };

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />

      <nav aria-label="Fil d'Ariane" className="mb-6 flex flex-wrap items-center gap-1 text-sm text-ink-muted">
        <Link href="/" className="hover:text-brand-700">Accueil</Link>
        <span>/</span>
        <Link href="/produits" className="hover:text-brand-700">Produits</Link>
        <span>/</span>
        <Link href={`/categories/${product.category.slug}`} className="hover:text-brand-700">{product.category.name}</Link>
      </nav>

      <div className="grid gap-8 lg:grid-cols-2">
        <ProductGallery images={product.images} productName={product.name} />

        <div>
          {product.brand && (
            <Link href={`/marques/${product.brand.slug}`} className="text-sm font-medium uppercase tracking-wide text-brand-700 hover:underline">
              {product.brand.name}
            </Link>
          )}

          <h1 className="mt-1 text-2xl font-bold text-ink sm:text-3xl">{product.name}</h1>

          {product.requires_prescription && (
            <p className="mt-2 text-sm font-medium text-[color:var(--color-warning)]">Nécessite une ordonnance</p>
          )}

          <div className="mt-4 flex items-center gap-3">
            <PriceTag price={product.price} compareAtPrice={product.compare_at_price} size="lg" />
            <StockBadge status={product.stock_status} />
          </div>

          {product.short_description && <p className="mt-4 text-ink-muted">{product.short_description}</p>}

          {product.tags.length > 0 && (
            <div className="mt-4 flex flex-wrap gap-2">
              {product.tags.map((tag) => (
                <Badge key={tag.id} tone="neutral">
                  {tag.name}
                </Badge>
              ))}
            </div>
          )}

          {product.description && (
            <div className="mt-8 border-t border-border pt-6">
              <h2 className="mb-2 text-sm font-semibold text-ink">Description</h2>
              <p className="whitespace-pre-line text-sm text-ink-muted">{product.description}</p>
            </div>
          )}

          <dl className="mt-8 grid grid-cols-2 gap-4 border-t border-border pt-6 text-sm">
            <div>
              <dt className="text-ink-muted">Référence</dt>
              <dd className="font-medium text-ink">{product.sku}</dd>
            </div>
            <div>
              <dt className="text-ink-muted">Catégorie</dt>
              <dd className="font-medium text-ink">{product.category.name}</dd>
            </div>
          </dl>
        </div>
      </div>
    </div>
  );
}
