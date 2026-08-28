import type { Metadata } from 'next';
import { PaymentResultView } from '@/components/payment/PaymentResultView';
import { getOrder } from '@/lib/api/orders';
import { getOptionalUser } from '@/lib/auth/server';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'Paiement',
  robots: { index: false },
};

function firstValue(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}

/**
 * Landing target after a (real or mock) Wave checkout reports success —
 * NEVER treated as proof on its own. If the visitor is authenticated,
 * the order is re-fetched here, server-side, with the session's Bearer
 * token; a guest is handed off to PaymentResultView, which re-proves
 * ownership via the phone stored in sessionStorage by CheckoutView.
 */
export default async function PaymentSuccessPage({ searchParams }: PageProps<'/paiement/succes'>) {
  const query = await searchParams;
  const orderNumber = firstValue(query.order);

  if (!orderNumber) {
    return <PaymentResultView orderNumber="" landedOn="success" initialOrder={null} />;
  }

  const { token } = await getOptionalUser();
  const initialOrder = token !== null ? await getOrder(orderNumber, { token }).catch(() => null) : null;

  return (
    <PaymentResultView
      orderNumber={orderNumber}
      landedOn="success"
      initialOrder={initialOrder}
      isAuthenticated={token !== null}
    />
  );
}
