import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';

export default function NotFound() {
  return (
    <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
      <EmptyState
        title="Page introuvable"
        description="Le produit, la catégorie ou la marque que vous cherchez n'existe pas ou n'est plus disponible."
        action={<Button href="/produits">Voir tous les produits</Button>}
      />
    </div>
  );
}
