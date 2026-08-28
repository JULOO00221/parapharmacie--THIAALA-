import type { Metadata } from 'next';
import { CheckoutView } from '@/components/checkout/CheckoutView';
import { getDeliveryZones } from '@/lib/api/delivery-zones';
import { getMyOrders } from '@/lib/api/orders';
import { getStores } from '@/lib/api/stores';
import { getOptionalUser } from '@/lib/auth/server';

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
  const { user, token } = await getOptionalUser();

  // Préremplissage nom/téléphone pour un compte connecté : le modèle User
  // n'a pas de champ téléphone (jamais collecté à l'inscription), donc on
  // réutilise sa dernière commande réelle (même endpoint authentifié que
  // /compte/commandes) comme meilleure source disponible plutôt que de
  // laisser le téléphone vide ou d'inventer un champ backend. Simple valeur
  // initiale de formulaire — les deux champs restent modifiables ensuite.
  const lastOrder =
    user !== null && token !== null
      ? await getMyOrders(token, { per_page: 1 })
          .then((response) => response.data[0] ?? null)
          .catch(() => null)
      : null;

  const prefillName = lastOrder?.customer.name ?? user?.name ?? '';
  const prefillPhone = lastOrder?.customer.phone ?? '';

  return (
    <CheckoutView
      stores={stores}
      deliveryZones={deliveryZones}
      isAuthenticated={user !== null}
      prefillName={prefillName}
      prefillPhone={prefillPhone}
    />
  );
}
