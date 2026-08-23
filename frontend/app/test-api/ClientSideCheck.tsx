'use client';

import { useEffect, useState } from 'react';
import { ApiError } from '@/lib/api/client';
import { getProducts } from '@/lib/api/products';

/**
 * Deliberately fetches from the BROWSER (not the Next.js server) so a CORS
 * misconfiguration on the Laravel side would actually show up here — an
 * SSR-only fetch would never reveal a CORS problem, since CORS only
 * applies to browser-initiated cross-origin requests.
 */
export function ClientSideCheck() {
  const [state, setState] = useState<
    { status: 'loading' } | { status: 'ok'; total: number } | { status: 'error'; message: string }
  >({ status: 'loading' });

  useEffect(() => {
    const controller = new AbortController();

    getProducts({ per_page: 1 }, { signal: controller.signal })
      .then((response) => setState({ status: 'ok', total: response.meta.total }))
      .catch((error: unknown) => {
        if (error instanceof ApiError) {
          setState({ status: 'error', message: `${error.status || 'réseau'}: ${error.message}` });
        } else {
          setState({ status: 'error', message: 'Erreur inconnue' });
        }
      });

    return () => controller.abort();
  }, []);

  return (
    <div className="rounded border border-dashed border-gray-400 p-4 text-sm">
      <p className="font-semibold">Appel côté navigateur (test CORS)</p>
      {state.status === 'loading' && <p>Chargement…</p>}
      {state.status === 'ok' && (
        <p className="text-green-700">
          ✓ Réussi depuis le navigateur — {state.total} produits au total. Aucune erreur CORS.
        </p>
      )}
      {state.status === 'error' && <p className="text-red-700">✗ Échec : {state.message}</p>}
    </div>
  );
}
