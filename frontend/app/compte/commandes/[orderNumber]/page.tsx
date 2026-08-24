import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { Button } from '@/components/ui/Button';
import { OrderDetail } from '@/components/orders/OrderDetail';
import { ApiError } from '@/lib/api/client';
import { getOrder } from '@/lib/api/orders';
import { requireUser } from '@/lib/auth/server';

export const metadata: Metadata = {
  title: 'Détail de la commande',
  robots: { index: false },
};

export default async function AccountOrderDetailPage({ params }: PageProps<'/compte/commandes/[orderNumber]'>) {
  const { orderNumber } = await params;
  const { token } = await requireUser();

  // Laravel garantit l'ownership (OrderController::canView) et renvoie
  // 404 — jamais 403 — pour toute commande qui n'appartient pas à cet
  // utilisateur : impossible de confirmer depuis ici qu'une commande
  // étrangère existe.
  let order;
  try {
    order = await getOrder(orderNumber, { token });
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      notFound();
    }
    throw error;
  }

  return (
    <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-bold text-ink sm:text-3xl">Commande {order.order_number}</h1>
        <Button href="/compte/commandes" variant="outline" size="sm">
          Retour
        </Button>
      </div>

      <div className="mt-8">
        <OrderDetail order={order} />
      </div>
    </div>
  );
}
