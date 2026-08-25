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
 * Landing target after a (real or mock) Wave checkout reports failure —
 * same re-verification discipline as /paiement/succes: the URL is never
 * trusted, only what Laravel actually returns. A failed-looking redirect
 * could in principle even correspond to an order confirmed moments later
 * by a duplicate/late webhook — PaymentResultView renders whatever the
 * fresh order state actually says, not what this route's name implies.
 */
export default async function PaymentFailurePage({ searchParams }: PageProps<'/paiement/echec'>) {
  const query = await searchParams;
  const orderNumber = firstValue(query.order);

  if (!orderNumber) {
    return <PaymentResultView orderNumber="" landedOn="failure" initialOrder={null} />;
  }

  const { token } = await getOptionalUser();
  const initialOrder = token !== null ? await getOrder(orderNumber, { token }).catch(() => null) : null;

  return <PaymentResultView orderNumber={orderNumber} landedOn="failure" initialOrder={initialOrder} />;
}
