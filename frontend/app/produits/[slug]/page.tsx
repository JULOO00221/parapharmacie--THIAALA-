import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { ProductAddToCart } from '@/components/cart/ProductAddToCart';
import { ProductSection } from '@/components/catalog/ProductSection';
import { Badge } from '@/components/ui/Badge';
import { PriceTag } from '@/components/product/PriceTag';
import { ProductGallery } from '@/components/product/ProductGallery';
import { ProductReassurance } from '@/components/product/ProductReassurance';
import { StockBadge } from '@/components/product/StockBadge';
import { ApiError } from '@/lib/api/client';
import { getProduct, getProducts } from '@/lib/api/products';
import { discountPercent } from '@/lib/utils/pricing';

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

  // Same real capability the category pages already use — no new endpoint.
  // Excludes the current product itself, capped to a small, curated set.
  const related = (await getProducts({ category: product.category.slug, per_page: 5 })).data
    .filter((candidate) => candidate.id !== product.id)
    .slice(0, 4);

  const percentOff = discountPercent(product.price, product.compare_at_price);

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

  // id targeted by ProductAddToCart's mobile sticky bar via a React portal —
  // `position: sticky` (not `fixed`) needs its containing block to span
  // this entire page's content (title through "Produits similaires") so it
  // releases naturally right before the real Footer instead of ever
  // covering it. See ProductAddToCart.tsx for why `fixed` + a spacer can
  // never actually achieve this.
  return (
    <div id="product-page-sticky-container">
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
          <ProductGallery
            images={product.images}
            productName={product.name}
            featured={product.featured}
            percentOff={percentOff}
          />

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

            <div className="mt-4 flex flex-wrap items-center gap-3">
              <PriceTag price={product.price} compareAtPrice={product.compare_at_price} size="lg" />
              {percentOff !== null && <Badge tone="promo">−{percentOff}%</Badge>}
              <StockBadge status={product.stock_status} />
            </div>

            {product.short_description && <p className="mt-4 text-ink-muted">{product.short_description}</p>}

            <ProductAddToCart product={product} />

            <ProductReassurance />

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
                <h2 className="mb-3 font-display text-lg font-semibold text-ink">Description</h2>
                <p className="whitespace-pre-line text-sm leading-relaxed text-ink-muted">{product.description}</p>
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

      <ProductSection
        title="Produits similaires"
        viewAllHref={`/categories/${product.category.slug}`}
        products={related}
      />
    </div>
  );
}
