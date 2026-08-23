import Link from 'next/link';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { ProductGrid } from '@/components/catalog/ProductGrid';
import { getCategories } from '@/lib/api/categories';
import { getProducts } from '@/lib/api/products';

export const dynamic = 'force-dynamic';

export default async function HomePage() {
  const [featured, categories] = await Promise.all([
    getProducts({ featured: true, per_page: 8 }),
    getCategories(),
  ]);

  const shoppableCategories = categories.filter((category) => (category.children ?? []).length === 0);

  return (
    <div>
      <section className="bg-brand-600">
        <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
          <p className="text-sm font-medium uppercase tracking-wide text-brand-100">Parapharmacie & cosmétiques</p>
          <h1 className="mt-3 max-w-xl text-3xl font-bold text-white sm:text-5xl">
            Des soins sélectionnés avec soin, pour tout le Sénégal.
          </h1>
          <p className="mt-4 max-w-lg text-brand-50">
            Visage, corps, cheveux et hygiène — découvrez le catalogue Tambacounda Cosmetix.
          </p>
          <Button href="/produits" variant="secondary" size="lg" className="mt-8">
            Voir le catalogue
          </Button>
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <h2 className="mb-6 text-xl font-bold text-ink sm:text-2xl">Nos catégories</h2>
        {shoppableCategories.length === 0 ? (
          <EmptyState title="Aucune catégorie disponible pour le moment" />
        ) : (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
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

      <section className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <div className="mb-6 flex items-center justify-between">
          <h2 className="text-xl font-bold text-ink sm:text-2xl">Mis en avant</h2>
          <Link href="/produits?featured=1" className="text-sm font-medium text-brand-700 hover:underline">
            Tout voir
          </Link>
        </div>
        <ProductGrid
          products={featured.data}
          emptyTitle="Aucun produit mis en avant pour le moment"
          emptyDescription="Découvrez tout de même l'ensemble du catalogue."
        />
      </section>

      <section className="mx-auto max-w-6xl px-4 pb-16 sm:px-6">
        <div className="rounded-2xl bg-accent-100 px-6 py-10 text-center">
          <h2 className="text-xl font-bold text-ink">Envie de tout explorer ?</h2>
          <p className="mt-2 text-ink-muted">Recherchez, filtrez et trouvez le produit qu&apos;il vous faut.</p>
          <Button href="/produits" variant="primary" className="mt-6">
            Voir tous les produits
          </Button>
        </div>
      </section>
    </div>
  );
}
