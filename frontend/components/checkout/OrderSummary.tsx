import type { CartItem } from '@/lib/cart/types';
import { formatPrice } from '@/lib/utils/format';

/**
 * Purely indicative — these figures never leave the browser as an
 * authoritative amount. Laravel recomputes subtotal/delivery_fee/total
 * from products.price and real stock at POST /orders time; the success
 * page displays only what that response actually returns.
 */
export function OrderSummary({
  items,
  subtotal,
  deliveryFee = 0,
}: {
  items: CartItem[];
  subtotal: number;
  /** Selected zone's fee (display-only estimate) — 0 for pickup. */
  deliveryFee?: number;
}) {
  const total = subtotal + deliveryFee;

  return (
    <div className="rounded-2xl border border-border bg-surface-raised p-4 sm:p-6">
      <h2 className="font-display text-lg font-semibold text-ink">Récapitulatif</h2>

      <ul className="mt-4 divide-y divide-border">
        {items.map((item) => (
          <li key={item.productId} className="flex items-center justify-between gap-3 py-3 first:pt-0">
            <div>
              <p className="text-sm font-medium text-ink">{item.name}</p>
              <p className="text-xs text-ink-muted">
                Qté {item.quantity} × {formatPrice(item.price)}
              </p>
            </div>
            <p className="shrink-0 text-sm font-medium text-ink">
              {formatPrice(Number.parseFloat(item.price) * item.quantity)}
            </p>
          </li>
        ))}
      </ul>

      <div className="mt-4 space-y-2 border-t border-border pt-4 text-sm">
        <div className="flex items-center justify-between text-ink-muted">
          <span>Sous-total</span>
          <span>{formatPrice(subtotal)}</span>
        </div>
        <div className="flex items-center justify-between text-ink-muted">
          <span>Livraison</span>
          <span>{formatPrice(deliveryFee)}</span>
        </div>
        <div className="flex items-center justify-between text-lg font-bold text-ink">
          <span>Total</span>
          <span className="text-brand-700">{formatPrice(total)}</span>
        </div>
      </div>

      <p className="mt-3 text-xs text-ink-muted">
        Montants indicatifs — le total définitif est confirmé par le serveur à la validation de la commande.
      </p>
    </div>
  );
}
