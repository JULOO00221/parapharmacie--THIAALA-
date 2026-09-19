import { EmptyState } from '@/components/ui/EmptyState';
import { ProductCard } from '@/components/product/ProductCard';
import type { Product } from '@/lib/api/types';

export function ProductGrid({
  products,
  emptyTitle = 'Aucun produit ne correspond à votre recherche',
  emptyDescription = 'Essayez d\'ajuster les filtres ou le terme recherché.',
}: {
  products: Product[];
  emptyTitle?: string;
  emptyDescription?: string;
}) {
  if (products.length === 0) {
    return <EmptyState title={emptyTitle} description={emptyDescription} />;
  }

  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 lg:gap-[22px]">
      {products.map((product) => (
        <ProductCard key={product.id} product={product} />
      ))}
    </div>
  );
}
