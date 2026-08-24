import type { Metadata } from 'next';
import Link from 'next/link';
import { Badge } from '@/components/ui/Badge';
import { EmptyState } from '@/components/ui/EmptyState';
import { Pagination } from '@/components/catalog/Pagination';
import { Button } from '@/components/ui/Button';
import { getMyOrders } from '@/lib/api/orders';
import { requireUser } from '@/lib/auth/server';
import { PAYMENT_STATUS_LABELS, STATUS_LABELS } from '@/lib/orders/labels';
import { formatPrice } from '@/lib/utils/format';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'Mes commandes',
  robots: { index: false },
};

function firstValue(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}

export default async function AccountOrdersPage({ searchParams }: PageProps<'/compte/commandes'>) {
  const { token } = await requireUser();
  const query = await searchParams;
  const pageParam = firstValue(query.page);
  const page = pageParam ? Number.parseInt(pageParam, 10) : undefined;

  const orders = await getMyOrders(token, { page });

  return (
    <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-bold text-ink sm:text-3xl">Mes commandes</h1>
        <Button href="/compte" variant="outline" size="sm">
          Retour au compte
        </Button>
      </div>

      {orders.data.length === 0 ? (
        <div className="mt-8">
          <EmptyState title="Aucune commande pour le moment" description="Vos commandes apparaîtront ici une fois passées." action={<Button href="/produits">Voir les produits</Button>} />
        </div>
      ) : (
        <>
          <div className="mt-8 overflow-x-auto">
            <table className="w-full min-w-[640px] text-sm">
              <thead>
                <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-ink-muted">
                  <th className="pb-2 font-medium">Numéro</th>
                  <th className="pb-2 font-medium">Date</th>
                  <th className="pb-2 font-medium">Mode</th>
                  <th className="pb-2 font-medium">Statut</th>
                  <th className="pb-2 font-medium">Paiement</th>
                  <th className="pb-2 text-right font-medium">Total</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {orders.data.map((order) => (
                  <tr key={order.order_number} className="hover:bg-brand-50">
                    <td className="py-3 pr-3">
                      <Link href={`/compte/commandes/${order.order_number}`} className="font-medium text-brand-700 hover:underline">
                        {order.order_number}
                      </Link>
                    </td>
                    <td className="py-3 pr-3 text-ink-muted">
                      {new Date(order.created_at).toLocaleDateString('fr-FR', { dateStyle: 'medium' })}
                    </td>
                    <td className="py-3 pr-3 text-ink">{order.delivery.is_pickup ? 'Retrait' : 'Livraison'}</td>
                    <td className="py-3 pr-3">
                      <Badge tone={order.status === 'cancelled' ? 'danger' : order.status === 'delivered' || order.status === 'ready' ? 'success' : 'neutral'}>
                        {STATUS_LABELS[order.status]}
                      </Badge>
                    </td>
                    <td className="py-3 pr-3">
                      <Badge tone={order.payment_status === 'paid' ? 'success' : 'neutral'}>{PAYMENT_STATUS_LABELS[order.payment_status]}</Badge>
                    </td>
                    <td className="py-3 text-right font-medium text-ink">{formatPrice(order.total)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <Pagination meta={orders.meta} basePath="/compte/commandes" searchParams={query} />
        </>
      )}
    </div>
  );
}
