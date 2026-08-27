import Link from 'next/link';
import { BrandsShowcase } from '@/components/home/BrandsShowcase';
import { Hero } from '@/components/home/Hero';
import { ReassuranceSection } from '@/components/home/ReassuranceSection';
import { EmptyState } from '@/components/ui/EmptyState';
import { ProductSection } from '@/components/catalog/ProductSection';
import { getBrands } from '@/lib/api/brands';
import { getCategories } from '@/lib/api/categories';
import { getProducts } from '@/lib/api/products';
import { isDiscounted } from '@/lib/utils/pricing';

export const dynamic = 'force-dynamic';

export default async function HomePage() {
  const [featured, categories, brands, catalog] = await Promise.all([
    getProducts({ featured: true, per_page: 8 }),
    getCategories(),
    getBrands(),
    // No dedicated "on sale" or "date added" filter is exposed by the public
    // API — but sort=newest already orders by created_at server-side
    // (ProductController@index), the same real field the catalogue's own
    // "Plus récents" sort option already relies on. One fetch covers both
    // Nouveautés and Promotions below by slicing/filtering that real data,
    // rather than adding a new endpoint or inventing a "freshness" heuristic.
    getProducts({ sort: 'newest', per_page: 100 }),
  ]);

  const shoppableCategories = categories.filter((category) => (category.children ?? []).length === 0);
  const newest = catalog.data.slice(0, 8);
  const promotions = catalog.data
    .filter((product) => isDiscounted(product.price, product.compare_at_price))
    .slice(0, 8);

  return (
    <div>
      <Hero showNouveautesCta={newest.length > 0} />

      <section className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <h2 className="font-display text-xl font-semibold text-ink sm:text-2xl">Nos catégories</h2>
        {shoppableCategories.length === 0 ? (
          <EmptyState title="Aucune catégorie disponible pour le moment" />
        ) : (
          <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            {shoppableCategories.map((category) => (
              <Link
                key={category.id}
                href={`/categories/${category.slug}`}
                className="rounded-xl border border-border bg-surface-raised px-4 py-6 text-center text-sm font-medium text-ink transition-colors hover:border-brand-400 hover:bg-brand-50"
              >
                {category.name}
              </Link>
            ))}
          </div>
        )}
      </section>

      <ProductSection
        title="Mis en avant"
        description="Une sélection de produits que nous aimons particulièrement."
        viewAllHref="/produits?featured=1"
        products={featured.data}
      />

      <ProductSection
        title="Promotions"
        description="Prix réduits, pour une durée limitée."
        viewAllHref="/produits"
        products={promotions}
      />

      <ProductSection
        id="nouveautes"
        title="Nouveautés"
        description="Les derniers produits arrivés dans notre catalogue."
        viewAllHref="/produits?sort=newest"
        products={newest}
      />

      <BrandsShowcase brands={brands} />

      <ReassuranceSection />
    </div>
  );
}
