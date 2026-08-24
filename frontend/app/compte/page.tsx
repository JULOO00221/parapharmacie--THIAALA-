import type { Metadata } from 'next';
import Link from 'next/link';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { getMyOrders } from '@/lib/api/orders';
import { logoutAction } from '@/lib/auth/actions';
import { requireUser } from '@/lib/auth/server';
import { STATUS_LABELS } from '@/lib/orders/labels';
import { formatPrice } from '@/lib/utils/format';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'Mon compte',
  robots: { index: false },
};

export default async function AccountPage() {
  const { user, token } = await requireUser();
  const orders = await getMyOrders(token, { per_page: 1 });
  const lastOrder = orders.data[0] ?? null;

  return (
    <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
      <h1 className="text-2xl font-bold text-ink sm:text-3xl">Bonjour {user.name}</h1>

      <section className="mt-8 rounded-2xl border border-border bg-surface-raised p-4 sm:p-6">
        <dl className="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-ink-muted">Nom</dt>
            <dd className="mt-0.5 text-ink">{user.name}</dd>
          </div>
          <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-ink-muted">Email</dt>
            <dd className="mt-0.5 text-ink">{user.email}</dd>
          </div>
          <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-ink-muted">Commandes passées</dt>
            <dd className="mt-0.5 text-ink">{orders.meta.total}</dd>
          </div>
        </dl>
      </section>

      {lastOrder && (
        <section className="mt-6 rounded-2xl border border-border bg-surface-raised p-4 sm:p-6">
          <h2 className="text-base font-semibold text-ink">Dernière commande</h2>
          <Link
            href={`/compte/commandes/${lastOrder.order_number}`}
            className="mt-3 flex items-center justify-between gap-3 rounded-xl border border-border p-4 hover:border-brand-600 hover:bg-brand-50"
          >
            <div>
              <p className="text-sm font-medium text-brand-700">{lastOrder.order_number}</p>
              <p className="mt-0.5 text-xs text-ink-muted">
                {new Date(lastOrder.created_at).toLocaleDateString('fr-FR', { dateStyle: 'medium' })}
              </p>
            </div>
            <div className="flex items-center gap-3">
              <Badge tone="neutral">{STATUS_LABELS[lastOrder.status]}</Badge>
              <span className="text-sm font-semibold text-ink">{formatPrice(lastOrder.total)}</span>
            </div>
          </Link>
        </section>
      )}

      <nav className="mt-8 flex flex-wrap items-center gap-3">
        <Button href="/compte/commandes" variant="outline">
          Mes commandes
        </Button>
        <form action={logoutAction}>
          <Button type="submit" variant="ghost">
            Déconnexion
          </Button>
        </form>
      </nav>
    </div>
  );
}
