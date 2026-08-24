import type { Metadata } from 'next';
import { CheckoutView } from '@/components/checkout/CheckoutView';
import { getStores } from '@/lib/api/stores';

// Boutiques toujours rechargées depuis Laravel — jamais mises en cache
// (comportement cohérent avec le reste du storefront, ex. /produits).
export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'Commande',
  alternates: { canonical: '/commande' },
};

export default async function CheckoutPage() {
  const stores = await getStores();

  return <CheckoutView stores={stores} />;
}
