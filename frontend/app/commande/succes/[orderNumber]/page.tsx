import type { Metadata } from 'next';
import { OrderSuccessView } from '@/components/checkout/OrderSuccessView';

export const metadata: Metadata = {
  title: 'Commande confirmée',
  robots: { index: false },
};

export default async function OrderSuccessPage({ params }: PageProps<'/commande/succes/[orderNumber]'>) {
  const { orderNumber } = await params;

  return <OrderSuccessView orderNumber={orderNumber} />;
}
