'use client';

import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { CartItemRow } from './CartItem';
import { useCart } from './CartProvider';
import { CartSummary } from './CartSummary';

export function CartPageView() {
  const { items, subtotal, clearCart } = useCart();

  return (
    <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
      <h1 className="font-display text-2xl font-bold text-ink sm:text-3xl">Mon panier</h1>

      {items.length === 0 ? (
        <div className="mt-8">
          <EmptyState
            title="Votre panier est vide"
            description="Parcourez le catalogue pour trouver vos produits."
            action={<Button href="/produits">Continuer mes achats</Button>}
          />
        </div>
      ) : (
        <div className="mt-8 flex flex-col gap-8 lg:flex-row lg:items-start">
          <ul className="flex-1 divide-y divide-border rounded-2xl border border-border bg-surface-raised px-4 sm:px-6">
            {items.map((item) => (
              <CartItemRow key={item.productId} item={item} />
            ))}
          </ul>

          <div className="w-full rounded-2xl border border-border bg-surface-raised p-4 sm:p-6 lg:w-80 lg:shrink-0">
            <CartSummary subtotal={subtotal} />

            <div className="mt-4 flex flex-col gap-2">
              {/* La page /commande n'existe pas encore (étape suivante de
                  la Phase 6) — ce lien est intentionnellement présent dès
                  maintenant, comme demandé. */}
              <Button href="/commande" className="w-full">
                Commander
              </Button>
              <button
                type="button"
                onClick={clearCart}
                className="text-xs font-medium text-ink-muted underline hover:text-ink"
              >
                Vider le panier
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
