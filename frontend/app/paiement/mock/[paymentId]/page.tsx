import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { MockWaveCheckout } from '@/components/payment/MockWaveCheckout';

export const metadata: Metadata = {
  title: 'Simulateur Wave',
  robots: { index: false, follow: false },
};

function firstValue(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}

/**
 * Never reachable in production — this page exists solely to stand in
 * for pay.wave.com while WAVE_MOCK is on. See MockWaveWebhookController
 * for the matching server-side guard.
 */
export default async function MockWaveCheckoutPage({
  params,
  searchParams,
}: PageProps<'/paiement/mock/[paymentId]'>) {
  if (process.env.NODE_ENV === 'production') {
    notFound();
  }

  const { paymentId } = await params;
  const query = await searchParams;
  const orderNumber = firstValue(query.order);

  if (!orderNumber) {
    notFound();
  }

  return <MockWaveCheckout transactionId={paymentId} orderNumber={orderNumber} />;
}
