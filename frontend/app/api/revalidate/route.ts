import { timingSafeEqual } from 'node:crypto';
import { revalidateTag } from 'next/cache';
import { CATALOG_CACHE_TAG } from '@/lib/api/client';

/**
 * POST /api/revalidate — appelé par Laravel après une modification du
 * catalogue (observer, job dédoublonné) et après chaque déploiement de
 * l'API (php artisan catalog:revalidate-frontend). Expire aussitôt le tag
 * `catalog` : la visite suivante relit l'API au lieu d'attendre jusqu'à
 * 5 minutes.
 *
 * Authentification : `Authorization: Bearer <REVALIDATE_SECRET>`. Le
 * secret n'existe que côté serveur (jamais NEXT_PUBLIC_) ; sans lui, la
 * route refuse tout plutôt que de rester ouverte.
 */
export async function POST(request: Request): Promise<Response> {
  const secret = process.env.REVALIDATE_SECRET ?? '';

  if (secret.length < 32) {
    console.error('[revalidate] REVALIDATE_SECRET absent ou trop court (32 caractères minimum) : route désactivée.');
    return Response.json({ message: 'Revalidation non configurée.' }, { status: 503 });
  }

  if (!isAuthorized(request.headers.get('authorization'), secret)) {
    return Response.json({ message: 'Non autorisé.' }, { status: 401 });
  }

  // { expire: 0 } : expiration immédiate, la forme que Next recommande pour
  // un webhook externe ('max' servirait encore une fois l'ancienne version).
  revalidateTag(CATALOG_CACHE_TAG, { expire: 0 });

  return Response.json({ revalidated: true, tag: CATALOG_CACHE_TAG, at: new Date().toISOString() });
}

/** Comparaison à temps constant : la durée de la réponse ne révèle rien du secret. */
function isAuthorized(header: string | null, secret: string): boolean {
  const match = header?.match(/^Bearer (.+)$/);
  if (!match) return false;

  const provided = Buffer.from(match[1]);
  const expected = Buffer.from(secret);

  return provided.length === expected.length && timingSafeEqual(provided, expected);
}
