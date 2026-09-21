import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { cache, type ReactNode } from 'react';
import { ProductAddToCart } from '@/components/cart/ProductAddToCart';
import { Breadcrumb, type BreadcrumbItem } from '@/components/catalog/Breadcrumb';
import { ProductSection } from '@/components/catalog/ProductSection';
import { PharmacistAdvice } from '@/components/layout/PharmacistAdvice';
import { ImageCredits } from '@/components/product/ImageCredits';
import { PriceTag } from '@/components/product/PriceTag';
import { ProductGallery } from '@/components/product/ProductGallery';
import { ProductReassurance } from '@/components/product/ProductReassurance';
import { ProductTabs, type ProductTab } from '@/components/product/ProductTabs';
import { StockBadge } from '@/components/product/StockBadge';
import { getCategories } from '@/lib/api/categories';
import { withFallback } from '@/lib/api/client';
import { getDeliveryZones } from '@/lib/api/delivery-zones';
import { getProduct, getProducts } from '@/lib/api/products';
import type { Category, DeliveryZone, Product } from '@/lib/api/types';
import { cheapestDeliveryZone } from '@/lib/utils/delivery';
import { discountPercent } from '@/lib/utils/pricing';
import { isRealBrand, visibleShortDescription } from '@/lib/utils/product';

export const dynamic = 'force-dynamic';

// cache() : generateMetadata et la page partagent un seul appel API par
// requête. Next ne déduplique pas ce fetch tout seul, car request() lui
// passe toujours un AbortSignal (timeout), ce qui désactive la
// mémoïsation automatique des fetch.
// getProduct() ne lève jamais : null = introuvable (404) ou API indisponible.
const loadProduct = cache(async (slug: string) => {
  const product = await getProduct(slug);

  if (product === null) {
    notFound();
  }

  return product;
});

export async function generateMetadata({ params }: PageProps<'/produits/[slug]'>): Promise<Metadata> {
  const { slug } = await params;
  const product = await loadProduct(slug);

  return {
    title: product.name,
    description:
      visibleShortDescription(product) ??
      `${product.name} à la Parapharmacie THIAALA, livré à Tambacounda et dans la région. Paiement à la livraison ou par Wave.`,
    alternates: { canonical: `/produits/${product.slug}` },
    openGraph: {
      title: product.name,
      description: visibleShortDescription(product) ?? undefined,
      images: product.primary_image ? [product.primary_image.url] : undefined,
      type: 'website',
    },
  };
}

/** Accueil / [rayon parent] / catégorie / produit — le parent vient de la liste des catégories (en cache). */
function breadcrumbFor(product: Product, categories: Category[]): BreadcrumbItem[] {
  const parent = categories.find((category) => category.slug === product.category.slug)?.parent;

  return [
    { label: 'Accueil', href: '/' },
    ...(parent ? [{ label: parent.name, href: `/categories/${parent.slug}` }] : []),
    { label: product.category.name, href: `/categories/${product.category.slug}` },
    { label: product.name },
  ];
}

function Characteristics({ product, categories }: { product: Product; categories: Category[] }) {
  const parent = categories.find((category) => category.slug === product.category.slug)?.parent;
  const rows: Array<{ label: string; value: ReactNode }> = [
    { label: 'Référence', value: product.sku },
    ...(product.brand && isRealBrand(product.brand)
      ? [
          {
            label: 'Marque',
            value: (
              <Link href={`/marques/${product.brand.slug}`} className="font-semibold text-vert hover:underline">
                {product.brand.name}
              </Link>
            ),
          },
        ]
      : []),
    {
      label: 'Catégorie',
      value: (
        <Link href={`/categories/${product.category.slug}`} className="font-semibold text-vert hover:underline">
          {parent ? `${parent.name} › ${product.category.name}` : product.category.name}
        </Link>
      ),
    },
    ...(product.tags.length > 0 ? [{ label: 'Tags', value: product.tags.map((tag) => tag.name).join(', ') }] : []),
  ];

  return (
    <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-3 text-[14.5px] lg:text-[15.5px]">
      {rows.map((row) => (
        <div key={row.label} className="contents">
          <dt className="text-texte-discret">{row.label}</dt>
          <dd className="text-encre">{row.value}</dd>
        </div>
      ))}
    </dl>
  );
}

export default async function ProductPage({ params }: PageProps<'/produits/[slug]'>) {
  const { slug } = await params;
  const product = await loadProduct(slug);

  const [relatedPage, categories, zones] = await Promise.all([
    // Même rayon (sous-catégories comprises), hors produit courant.
    getProducts({ category: product.category.slug, per_page: 5 }),
    // Liste en cache, partagée avec l'en-tête : sert au fil d'Ariane.
    getCategories(),
    // Lève en cas d'erreur (le paiement la gère) ; ici, l'encadré se replie
    // simplement sur « frais selon la zone ».
    withFallback<DeliveryZone[]>('GET /delivery-zones', [], () => getDeliveryZones()),
  ]);
  const related = relatedPage.data.filter((candidate) => candidate.id !== product.id).slice(0, 4);

  const percentOff = discountPercent(product.price, product.compare_at_price);
  const shortDescription = visibleShortDescription(product);
  const cheapestZone = cheapestDeliveryZone(zones);

  // Onglets : uniquement ceux qui ont du contenu. Composition et Conseils
  // d'utilisation apparaîtront quand l'API les fournira (DESIGN.md §5).
  const tabs: ProductTab[] = [
    ...(product.description
      ? [
          {
            id: 'description',
            label: 'Description',
            content: (
              <p className="whitespace-pre-line text-[14.5px] leading-[1.7] text-texte-doux lg:text-[15.5px] lg:leading-[1.75]">
                {product.description}
              </p>
            ),
          },
        ]
      : []),
    { id: 'caracteristiques', label: 'Caractéristiques', content: <Characteristics product={product} categories={categories} /> },
  ];

  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: product.name,
    description: shortDescription ?? product.description ?? undefined,
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

  // id ciblé par la barre d'achat mobile de ProductAddToCart (portail React) :
  // `position: sticky` a besoin d'un conteneur couvrant toute la page, du
  // titre aux produits similaires, pour se libérer juste avant le pied de
  // page au lieu de le recouvrir. Voir ProductAddToCart.tsx.
  return (
    <div id="product-page-sticky-container">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />

      <div className="mx-auto max-w-[1440px] px-4 pt-4 sm:px-8 lg:px-16 lg:pt-5">
        <div className="pb-4 lg:pb-[14px]">
          <Breadcrumb items={breadcrumbFor(product, categories)} />
        </div>

        <div className="grid gap-[22px] lg:grid-cols-[minmax(0,620px)_minmax(0,1fr)] lg:gap-14">
          <div className="flex flex-col gap-2.5">
            <ProductGallery images={product.images} productName={product.name} featured={product.featured} percentOff={percentOff} />
            {/* Crédit photo : obligatoire sous CC-BY-SA, donc rendu au plus
                près de l'image, pas relégué en pied de page. */}
            <ImageCredits images={product.images} />
          </div>

          <div className="flex flex-col gap-[13px] lg:gap-[18px]">
            {product.brand && isRealBrand(product.brand) && (
              <Link
                href={`/marques/${product.brand.slug}`}
                className="surtitre w-fit text-[10.5px] tracking-[0.18em] text-or hover:text-vert lg:text-xs"
              >
                {product.brand.name}
              </Link>
            )}

            <h1 className="font-titre text-[30px] leading-[1.12] text-vert lg:text-[42px]">{product.name}</h1>

            <p className="text-[14.5px] leading-relaxed text-texte-doux lg:text-base">
              {shortDescription ? <>{shortDescription} </> : null}
              <span className="text-texte-discret">Réf. {product.sku}</span>
            </p>

            {/* Le pourcentage de remise est déjà sur le visuel (ProductBadges). */}
            <div className="lg:pt-1">
              <PriceTag price={product.price} compareAtPrice={product.compare_at_price} size="lg" />
            </div>

            <StockBadge status={product.stock_status} />

            {product.requires_prescription && (
              <p className="rounded-xl border border-bordure bg-blanc px-4 py-3 text-sm font-medium text-warning">
                Ce produit nécessite une ordonnance.
              </p>
            )}

            <div className="pt-1.5">
              <ProductAddToCart product={product} />
            </div>

            <div className="pt-[9px] lg:pt-1.5">
              <ProductReassurance cheapestDeliveryFee={cheapestZone?.fee ?? null} />
            </div>
          </div>
        </div>

        <div className="grid gap-[26px] pt-[26px] lg:grid-cols-[minmax(0,620px)_minmax(0,1fr)] lg:items-start lg:gap-14 lg:pt-[66px]">
          <ProductTabs tabs={tabs} />
          <PharmacistAdvice layout="card" productName={product.name} />
        </div>
      </div>

      <ProductSection
        eyebrow="Dans le même rayon"
        title="Vous aimerez aussi"
        viewAllHref={`/categories/${product.category.slug}`}
        products={related}
      />

      {/* Espace sous la dernière section : la barre d'achat mobile ne recouvre jamais une carte. */}
      <div className="h-8 lg:h-0" aria-hidden="true" />
    </div>
  );
}
