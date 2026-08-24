'use client';

import { useEffect, useReducer } from 'react';
import { Button } from '@/components/ui/Button';
import { ApiError } from '@/lib/api/client';
import { getOrder } from '@/lib/api/orders';
import type { Order, OrderStatus } from '@/lib/api/types';
import { formatPrice } from '@/lib/utils/format';

/** Must match the key CheckoutView writes right before redirecting here. */
const LAST_ORDER_KEY_PREFIX = 'tambacounda-cosmetix:order:';

const STATUS_LABELS: Record<OrderStatus, string> = {
  pending: 'En attente de confirmation',
  confirmed: 'Confirmée',
  preparing: 'En préparation',
  ready: 'Prête pour le retrait',
  delivered: 'Retirée',
  cancelled: 'Annulée',
};

const PAYMENT_METHOD_LABELS: Record<Order['payment_method'], string> = {
  cash_in_store: 'Paiement à la boutique',
  cash_on_delivery: 'Paiement à la livraison',
};

type LoadState =
  | { status: 'loading' }
  | { status: 'found'; order: Order }
  | { status: 'error'; message: string };

// Reducer trivial : dispatch() dans un effet est idiomatique, alors que la
// règle react-hooks/set-state-in-effect interdit un setState direct — même
// pattern que CartProvider pour l'hydratation localStorage.
function loadStateReducer(_state: LoadState, action: LoadState): LoadState {
  return action;
}

export function OrderSuccessView({ orderNumber }: { orderNumber: string }) {
  const [state, dispatch] = useReducer(loadStateReducer, { status: 'loading' });

  useEffect(() => {
    // Chemin normal : la commande vient d'être créée par /commande et sa
    // réponse complète (déjà renvoyée par Laravel) a été stockée
    // localement — pas de nouvel appel réseau nécessaire.
    try {
      const stored = window.sessionStorage.getItem(`${LAST_ORDER_KEY_PREFIX}${orderNumber}`);
      if (stored) {
        dispatch({ status: 'found', order: JSON.parse(stored) as Order });
        return;
      }
    } catch {
      // sessionStorage indisponible ou contenu corrompu : on retombe sur
      // le fallback réseau ci-dessous plutôt que de faire planter la page.
    }

    // Chargement direct de cette URL (rafraîchissement, lien partagé) :
    // ne peut réussir que si l'utilisateur est authentifié et propriétaire
    // — un invité n'a pas de moyen de prouver son téléphone à cet endroit.
    getOrder(orderNumber)
      .then((data) => dispatch({ status: 'found', order: data }))
      .catch((error) => {
        const message =
          error instanceof ApiError && error.status === 404
            ? "Nous n'avons pas pu récupérer automatiquement le détail de cette commande. Vous pourrez la consulter avec votre numéro de commande et votre numéro de téléphone."
            : 'Impossible de récupérer les détails de la commande pour le moment.';

        dispatch({ status: 'error', message });
      });
  }, [orderNumber]);

  const loading = state.status === 'loading';
  const order = state.status === 'found' ? state.order : null;
  const fallbackMessage = state.status === 'error' ? state.message : null;

  return (
    <div className="mx-auto max-w-2xl px-4 py-10 sm:px-6">
      <div className="rounded-2xl border border-border bg-surface-raised p-6 text-center sm:p-8">
        <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-700">
          <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2" className="h-6 w-6">
            <path d="M4 10.5l4 4 8-9" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </div>

        <h1 className="mt-4 text-2xl font-bold text-ink">Commande enregistrée</h1>

        <p className="mt-3 text-sm text-ink-muted">Numéro de commande</p>
        <p className="text-lg font-semibold text-brand-700">{orderNumber}</p>

        {loading ? (
          <p className="mt-6 border-t border-border pt-6 text-sm text-ink-muted">Chargement des détails…</p>
        ) : order ? (
          <dl className="mt-6 grid grid-cols-1 gap-4 border-t border-border pt-6 text-left sm:grid-cols-2">
            <div>
              <dt className="text-xs font-medium uppercase tracking-wide text-ink-muted">Client</dt>
              <dd className="mt-0.5 text-sm font-medium text-ink">{order.customer.name}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium uppercase tracking-wide text-ink-muted">Total</dt>
              <dd className="mt-0.5 text-sm font-medium text-ink">{formatPrice(order.total)}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium uppercase tracking-wide text-ink-muted">Mode</dt>
              <dd className="mt-0.5 text-sm font-medium text-ink">
                {order.delivery.is_pickup ? 'Retrait en boutique' : 'Livraison'}
              </dd>
            </div>
            <div>
              <dt className="text-xs font-medium uppercase tracking-wide text-ink-muted">Boutique</dt>
              <dd className="mt-0.5 text-sm font-medium text-ink">{order.store.name}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium uppercase tracking-wide text-ink-muted">Paiement</dt>
              <dd className="mt-0.5 text-sm font-medium text-ink">{PAYMENT_METHOD_LABELS[order.payment_method]}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium uppercase tracking-wide text-ink-muted">Statut</dt>
              <dd className="mt-0.5 text-sm font-medium text-ink">{STATUS_LABELS[order.status]}</dd>
            </div>
          </dl>
        ) : (
          <p className="mt-6 border-t border-border pt-6 text-sm text-ink-muted">{fallbackMessage}</p>
        )}

        <div className="mt-6 space-y-1.5 border-t border-border pt-6 text-sm text-ink-muted">
          <p>Conservez votre numéro de commande.</p>
          <p>Vous pourrez retrouver votre commande avec votre numéro de commande et votre numéro de téléphone.</p>
        </div>

        <Button href="/produits" className="mt-6 w-full sm:w-auto">
          Continuer mes achats
        </Button>
      </div>
    </div>
  );
}
