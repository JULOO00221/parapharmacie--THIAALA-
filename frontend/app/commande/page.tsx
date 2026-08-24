import type { Metadata } from 'next';
import { CheckoutView } from '@/components/checkout/CheckoutView';
import { getDeliveryZones } from '@/lib/api/delivery-zones';
import { getStores } from '@/lib/api/stores';
import { getCurrentUser } from '@/lib/auth/server';

// Boutiques et zones toujours rechargées depuis Laravel — jamais mises
// en cache (comportement cohérent avec le reste du storefront, ex. /produits).
export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'Commande',
  alternates: { canonical: '/commande' },
};

export default async function CheckoutPage() {
  const stores = await getStores();
  // Sans boutique le checkout ne peut de toute façon pas fonctionner (page
  // gérée plus bas par CheckoutView) — mais l'absence de zones ne doit
  // jamais bloquer le retrait en boutique : un échec de chargement des
  // zones dégrade simplement vers "livraison indisponible", pas vers une
  // page en erreur.
  const deliveryZones = await getDeliveryZones().catch(() => []);
  // Uniquement pour choisir le chemin d'envoi de la commande (direct
  // Laravel pour un invité, proxy /api/account/orders pour attacher le
  // Bearer côté serveur pour un connecté) — jamais le token lui-même.
  const user = await getCurrentUser();

  return <CheckoutView stores={stores} deliveryZones={deliveryZones} isAuthenticated={user !== null} />;
}
