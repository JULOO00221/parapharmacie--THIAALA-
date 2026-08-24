import { Badge } from '@/components/ui/Badge';
import type { Order } from '@/lib/api/types';
import { PAYMENT_METHOD_LABELS, PAYMENT_STATUS_LABELS, STATUS_LABELS } from '@/lib/orders/labels';
import { formatPrice } from '@/lib/utils/format';
import { OrderStatusTimeline } from './OrderStatusTimeline';

const STATUS_BADGE_TONE: Record<Order['status'], 'success' | 'warning' | 'danger' | 'neutral'> = {
  pending: 'neutral',
  confirmed: 'neutral',
  preparing: 'warning',
  ready: 'success',
  delivered: 'success',
  cancelled: 'danger',
};

/**
 * Shared detail view for /compte/commandes/[orderNumber] and the
 * /suivi-commande guest lookup result — every value comes straight from
 * Laravel's OrderResource, never recomputed here (see the "montants
 * indicatifs" precedent in OrderSummary during checkout: this page shows
 * only server-confirmed figures, no client-side arithmetic at all).
 */
export function OrderDetail({ order }: { order: Order }) {
  return (
    <div className="space-y-6">
      <section className="rounded-2xl border border-border bg-surface-raised p-4 sm:p-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="text-sm text-ink-muted">Numéro de commande</p>
            <p className="text-lg font-semibold text-brand-700">{order.order_number}</p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Badge tone={STATUS_BADGE_TONE[order.status]}>{STATUS_LABELS[order.status]}</Badge>
            <Badge tone={order.payment_status === 'paid' ? 'success' : 'neutral'}>
              {PAYMENT_STATUS_LABELS[order.payment_status]}
            </Badge>
          </div>
        </div>
        <p className="mt-2 text-sm text-ink-muted">
          {new Date(order.created_at).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' })}
        </p>

        <div className="mt-6 border-t border-border pt-6">
          <OrderStatusTimeline status={order.status} />
        </div>
      </section>

      <section className="rounded-2xl border border-border bg-surface-raised p-4 sm:p-6">
        <h2 className="text-base font-semibold text-ink">Produits</h2>
        <div className="mt-4 overflow-x-auto">
          <table className="w-full min-w-[480px] text-sm">
            <thead>
              <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-ink-muted">
                <th className="pb-2 font-medium">Produit</th>
                <th className="pb-2 font-medium">SKU</th>
                <th className="pb-2 text-right font-medium">Qté</th>
                <th className="pb-2 text-right font-medium">Prix unitaire</th>
                <th className="pb-2 text-right font-medium">Sous-total</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {order.items.map((item) => (
                <tr key={`${item.product_id ?? 'deleted'}-${item.sku}`}>
                  <td className="py-2.5 pr-3 text-ink">{item.product_name}</td>
                  <td className="py-2.5 pr-3 text-ink-muted">{item.sku}</td>
                  <td className="py-2.5 text-right text-ink">{item.quantity}</td>
                  <td className="py-2.5 text-right text-ink">{formatPrice(item.unit_price)}</td>
                  <td className="py-2.5 text-right font-medium text-ink">{formatPrice(item.subtotal)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <section className="rounded-2xl border border-border bg-surface-raised p-4 sm:p-6">
        <h2 className="text-base font-semibold text-ink">Livraison</h2>
        <dl className="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-ink-muted">Mode</dt>
            <dd className="mt-0.5 text-ink">{order.delivery.is_pickup ? 'Retrait en boutique' : 'Livraison'}</dd>
          </div>
          <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-ink-muted">Boutique</dt>
            <dd className="mt-0.5 text-ink">{order.store.name}</dd>
          </div>
          {!order.delivery.is_pickup && order.delivery.zone && (
            <div>
              <dt className="text-xs font-medium uppercase tracking-wide text-ink-muted">Zone</dt>
              <dd className="mt-0.5 text-ink">{order.delivery.zone.name}</dd>
            </div>
          )}
          {!order.delivery.is_pickup && order.delivery.address && (
            <div className="sm:col-span-2">
              <dt className="text-xs font-medium uppercase tracking-wide text-ink-muted">Adresse</dt>
              <dd className="mt-0.5 text-ink">{order.delivery.address}</dd>
            </div>
          )}
          <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-ink-muted">Paiement</dt>
            <dd className="mt-0.5 text-ink">{PAYMENT_METHOD_LABELS[order.payment_method]}</dd>
          </div>
        </dl>
      </section>

      <section className="rounded-2xl border border-border bg-surface-raised p-4 sm:p-6">
        <h2 className="text-base font-semibold text-ink">Total</h2>
        <div className="mt-4 space-y-2 text-sm">
          <div className="flex items-center justify-between text-ink-muted">
            <span>Sous-total</span>
            <span>{formatPrice(order.subtotal)}</span>
          </div>
          <div className="flex items-center justify-between text-ink-muted">
            <span>Livraison</span>
            <span>{formatPrice(order.delivery_fee)}</span>
          </div>
          <div className="flex items-center justify-between border-t border-border pt-2 text-base font-semibold text-ink">
            <span>Total</span>
            <span>{formatPrice(order.total)}</span>
          </div>
        </div>
      </section>
    </div>
  );
}
