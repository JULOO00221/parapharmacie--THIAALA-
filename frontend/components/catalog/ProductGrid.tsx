import type { ReactNode } from 'react';
import { EmptyState } from '@/components/ui/EmptyState';
import { ProductCard } from '@/components/product/ProductCard';
import type { Product } from '@/lib/api/types';

export function ProductGrid({
  products,
  emptyTitle = 'Aucun produit ne correspond à votre recherche',
  emptyDescription = 'Essayez d\'ajuster les filtres ou le terme recherché.',
  emptyAction,
  layout = 'wide',
}: {
  products: Product[];
  emptyTitle?: string;
  emptyDescription?: string;
  emptyAction?: ReactNode;
  /** `wide` : pleine largeur (accueil, produits similaires). `catalog` : à côté de la colonne de filtres. */
  layout?: 'wide' | 'catalog';
}) {
  if (products.length === 0) {
    return <EmptyState title={emptyTitle} description={emptyDescription} action={emptyAction} />;
  }

  return (
    <div
      className={
        layout === 'catalog'
          ? 'grid grid-cols-2 gap-3 xl:grid-cols-3 xl:gap-[22px]'
          : 'grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 lg:gap-[22px]'
      }
    >
      {products.map((product) => (
        <ProductCard key={product.id} product={product} />
      ))}
    </div>
  );
}
