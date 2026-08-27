import { formatPrice } from '@/lib/utils/format';

/**
 * Sous-total affiché à titre indicatif uniquement (somme prix × quantité
 * des snapshots locaux). Les frais de livraison et le total définitif
 * sont calculés côté Laravel au moment de la commande — jamais ici.
 */
export function CartSummary({ subtotal }: { subtotal: number }) {
  return (
    <div>
      <div className="flex items-center justify-between text-lg font-bold text-ink">
        <span>Sous-total</span>
        <span className="text-brand-700">{formatPrice(subtotal)}</span>
      </div>
      <p className="mt-1 text-xs text-ink-muted">
        Frais de livraison et total définitif calculés à l&apos;étape suivante.
      </p>
    </div>
  );
}
