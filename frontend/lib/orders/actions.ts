'use server';

import { ApiError } from '../api/client';
import { getOrder } from '../api/orders';
import type { TrackOrderState } from './tracking-state';

/**
 * Guest order lookup: numéro de commande + téléphone envoyés en POST
 * (jamais dans l'URL). Un échec — mauvaise combinaison, commande
 * inexistante — reçoit toujours le même message générique : Laravel
 * renvoie 404 dans les deux cas (jamais 403), donc ce n'est jamais
 * confirmable depuis l'extérieur qu'une commande existe.
 */
export async function trackOrderAction(_prevState: TrackOrderState, formData: FormData): Promise<TrackOrderState> {
  const orderNumber = String(formData.get('order_number') ?? '').trim();
  const phone = String(formData.get('phone') ?? '').trim();

  if (!orderNumber || !phone) {
    return { status: 'error', message: 'Merci de renseigner le numéro de commande et le numéro de téléphone.' };
  }

  try {
    const order = await getOrder(orderNumber, { phone });
    return { status: 'found', order };
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      return {
        status: 'error',
        message:
          'Aucune commande trouvée avec ces informations. Vérifiez le numéro de commande et le numéro de téléphone.',
      };
    }

    return { status: 'error', message: 'Impossible de vérifier cette commande pour le moment. Réessayez plus tard.' };
  }
}
