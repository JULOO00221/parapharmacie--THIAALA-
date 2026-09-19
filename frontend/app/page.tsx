import { ProductSection } from '@/components/catalog/ProductSection';
import { BrandsShowcase } from '@/components/home/BrandsShowcase';
import { CategoryGrid } from '@/components/home/CategoryGrid';
import { DeliveryZonesSection } from '@/components/home/DeliveryZonesSection';
import { Hero } from '@/components/home/Hero';
import { ReassuranceSection } from '@/components/home/ReassuranceSection';
import { PharmacistAdvice } from '@/components/layout/PharmacistAdvice';
import { getBrands } from '@/lib/api/brands';
import { getCategories } from '@/lib/api/categories';
import { withFallback } from '@/lib/api/client';
import { getDeliveryZones } from '@/lib/api/delivery-zones';
import { getProducts } from '@/lib/api/products';
import type { DeliveryZone, Product } from '@/lib/api/types';
import { isRealBrand } from '@/lib/utils/product';

export const dynamic = 'force-dynamic';

const SELECTION_SIZE = 4;

/**
 * Sélection de l'accueil : les produits mis en avant d'abord, complétés par
 * les plus récents (sort=newest, le même tri que « Plus récents » au
 * catalogue) quand il y en a moins que SELECTION_SIZE.
 */
function buildSelection(featured: Product[], newest: Product[]): Product[] {
  const featuredIds = new Set(featured.map((product) => product.id));

  return [...featured, ...newest.filter((product) => !featuredIds.has(product.id))].slice(0, SELECTION_SIZE);
}

export default async function HomePage() {
  const [featured, newest, categories, brands, zones] = await Promise.all([
    getProducts({ featured: true, per_page: SELECTION_SIZE }),
    getProducts({ sort: 'newest', per_page: SELECTION_SIZE * 2 }),
    getCategories(),
    getBrands(),
    // getDeliveryZones() lève en cas d'erreur (le checkout gère l'erreur
    // lui-même) ; ici, une panne d'API masque simplement les zones.
    withFallback<DeliveryZone[]>('GET /delivery-zones', [], () => getDeliveryZones()),
  ]);

  return (
    <div className="pb-2">
      <Hero brandCount={brands.filter(isRealBrand).length} />
      <ReassuranceSection zones={zones} />
      <CategoryGrid categories={categories} />
      <ProductSection
        id="selection"
        eyebrow="Notre sélection"
        title="Nos produits du moment"
        viewAllHref="/produits"
        products={buildSelection(featured.data, newest.data)}
      />
      <BrandsShowcase brands={brands} />
      <PharmacistAdvice />
      <DeliveryZonesSection zones={zones} />
    </div>
  );
}
