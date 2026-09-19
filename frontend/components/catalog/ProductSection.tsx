import { SectionHeading } from '@/components/ui/SectionHeading';
import type { Product } from '@/lib/api/types';
import { ProductGrid } from './ProductGrid';

/**
 * Section produits (sélection de l'accueil, produits similaires de la fiche
 * produit). Se masque entièrement quand elle n'a aucun produit réel à
 * montrer, plutôt que d'afficher un titre au-dessus du vide.
 */
export function ProductSection({
  id,
  eyebrow,
  title,
  viewAllHref,
  products,
}: {
  id?: string;
  eyebrow: string;
  title: string;
  viewAllHref?: string;
  products: Product[];
}) {
  if (products.length === 0) return null;

  const headingId = id ? `${id}-title` : undefined;

  return (
    <section
      id={id}
      aria-labelledby={headingId}
      className="mx-auto max-w-[1440px] px-5 pt-[34px] sm:px-8 lg:px-16 lg:pt-[78px]"
    >
      <SectionHeading
        id={headingId}
        eyebrow={eyebrow}
        title={title}
        link={viewAllHref ? { href: viewAllHref, label: 'Tout voir' } : undefined}
      />

      <div className="mt-[18px] lg:mt-[34px]">
        <ProductGrid products={products} />
      </div>
    </section>
  );
}
