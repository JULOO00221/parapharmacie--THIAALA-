'use client';

import { useEffect, useReducer, useRef, useState } from 'react';
import { useCart } from '@/components/cart/CartProvider';
import { Button } from '@/components/ui/Button';
import { getOrder } from '@/lib/api/orders';
import { initiatePayment, initiatePaymentViaAccount } from '@/lib/api/payments';
import type { Order } from '@/lib/api/types';
import { formatPrice } from '@/lib/utils/format';

const PAYMENT_PHONE_KEY_PREFIX = 'tambacounda-cosmetix:payment-phone:';

type LoadState =
  | { status: 'loading' }
  | { status: 'resolved'; order: Order; guestPhone: string | null }
  | { status: 'unresolvable' };

function loadStateReducer(_state: LoadState, action: LoadState): LoadState {
  return action;
}

/**
 * Shared by /paiement/succes and /paiement/echec — landedOn is only ever
 * used before real data has loaded (a loading-state hint), never as
 * proof of anything: the actual outcome shown always comes from
 * order.payment_status/order.status as freshly confirmed by Laravel,
 * exactly as required — the URL a Wave (or the mock) redirect used is
 * never trusted on its own.
 */
export function PaymentResultView({
  orderNumber,
  landedOn,
  initialOrder,
  isAuthenticated = false,
}: {
  orderNumber: string;
  landedOn: 'success' | 'failure';
  /** Already fetched server-side with the session's Bearer token — only set for an authenticated visitor. */
  initialOrder: Order | null;
  /** Chosen server-side from the session cookie, same pattern as CheckoutView — picks which existing payments endpoint a retry reuses. */
  isAuthenticated?: boolean;
}) {
  const { clearCart } = useCart();
  const [state, dispatch] = useReducer(
    loadStateReducer,
    initialOrder !== null ? { status: 'resolved', order: initialOrder, guestPhone: null } : { status: 'loading' }
  );

  useEffect(() => {
    if (initialOrder !== null) return;

    // Chemin invité : la seule preuve d'appartenance disponible ici est
    // le téléphone déposé par CheckoutView en sessionStorage avant la
    // redirection — jamais transmis dans une URL. Conservé dans le state
    // (pas juste utilisé pour l'appel) car un retry éventuel en aura besoin.
    let phone: string | null = null;
    try {
      phone = window.sessionStorage.getItem(`${PAYMENT_PHONE_KEY_PREFIX}${orderNumber}`);
    } catch {
      // sessionStorage indisponible — bascule sur l'état "non vérifiable" ci-dessous.
    }

    if (!phone) {
      dispatch({ status: 'unresolvable' });
      return;
    }

    getOrder(orderNumber, { phone })
      .then((order) => dispatch({ status: 'resolved', order, guestPhone: phone }))
      .catch(() => dispatch({ status: 'unresolvable' }));
  }, [orderNumber, initialOrder]);

  // Le panier n'est vidé QUE lorsque le paiement est réellement confirmé
  // payé par le serveur — jamais avant, jamais parce que l'utilisateur
  // est simplement revenu sur cette page, jamais parce qu'un retry a été
  // lancé (seule une nouvelle visite de cette page après confirmation
  // fraîche du serveur peut déclencher ce clearCart()).
  useEffect(() => {
    if (state.status === 'resolved' && state.order.payment_status === 'paid') {
      clearCart();
    }
  }, [state, clearCart]);

  return (
    <div className="mx-auto max-w-2xl px-4 py-10 sm:px-6">
      <div className="rounded-2xl border border-border bg-surface-raised p-6 text-center sm:p-8">
        {state.status === 'loading' && (
          <>
            <h1 className="font-display text-xl font-bold text-ink">Vérification du paiement…</h1>
            <p className="mt-3 text-sm text-ink-muted">
              Nous confirmons l&apos;état de votre paiement auprès du serveur, merci de patienter.
            </p>
          </>
        )}

        {state.status === 'unresolvable' && (
          <>
            <h1 className="font-display text-xl font-bold text-ink">Impossible de vérifier automatiquement</h1>
            <p className="mt-3 text-sm text-ink-muted">
              Nous n&apos;avons pas pu confirmer automatiquement l&apos;état de cette commande depuis cet appareil.
              Vous pouvez la retrouver avec votre numéro de commande et votre numéro de téléphone.
            </p>
            <p className="mt-3 text-sm font-medium text-ink">{orderNumber}</p>
            <Button href="/suivi-commande" className="mt-6">
              Suivre ma commande
            </Button>
          </>
        )}

        {state.status === 'resolved' && (
          <ResolvedResult
            order={state.order}
            landedOn={landedOn}
            isAuthenticated={isAuthenticated}
            guestPhone={state.guestPhone}
          />
        )}
      </div>
    </div>
  );
}

function ResolvedResult({
  order,
  landedOn,
  isAuthenticated,
  guestPhone,
}: {
  order: Order;
  landedOn: 'success' | 'failure';
  isAuthenticated: boolean;
  guestPhone: string | null;
}) {
  if (order.payment_status === 'paid') {
    return (
      <>
        <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-700">
          <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2" className="h-6 w-6">
            <path d="M4 10.5l4 4 8-9" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </div>
        <h1 className="mt-4 font-display text-2xl font-bold text-ink">Paiement confirmé</h1>
        <p className="mt-3 text-sm text-ink-muted">Numéro de commande</p>
        <p className="text-lg font-semibold text-brand-700">{order.order_number}</p>
        <p className="mt-4 text-sm text-ink-muted">Montant réglé : {formatPrice(order.total)}</p>
        <Button href="/produits" className="mt-6 w-full sm:w-auto">
          Continuer mes achats
        </Button>
      </>
    );
  }

  if (order.status === 'cancelled') {
    return (
      <>
        <h1 className="font-display text-xl font-bold text-ink">Commande annulée</h1>
        <p className="mt-3 text-sm text-ink-muted">
          Le paiement n&apos;a pas abouti à temps et la commande {order.order_number} a été annulée. Vous pouvez
          recommencer votre commande.
        </p>
        <Button href="/produits" className="mt-6">
          Retour aux produits
        </Button>
      </>
    );
  }

  return (
    <RetryableFailure order={order} landedOn={landedOn} isAuthenticated={isAuthenticated} guestPhone={guestPhone} />
  );
}

/**
 * order.status is still 'pending' here (the two terminal branches above —
 * paid / cancelled — already returned) so the order genuinely remains
 * payable: PaymentService.initiate() (via initiatePayment /
 * initiatePaymentViaAccount, the exact same functions CheckoutView already
 * calls) only ever reuses or creates a Payment row on this SAME order —
 * it never creates a new Order. No new backend endpoint was needed or
 * added for this retry; every protection (idempotent Payment reuse while
 * one is still active, OrderNotPayableException once truly cancelled,
 * amount pinned server-side from order.total) is the existing
 * PaymentService/OrderService logic, untouched here.
 */
function RetryableFailure({
  order,
  landedOn,
  isAuthenticated,
  guestPhone,
}: {
  order: Order;
  landedOn: 'success' | 'failure';
  isAuthenticated: boolean;
  guestPhone: string | null;
}) {
  const [retrying, setRetrying] = useState(false);
  const [retryError, setRetryError] = useState<string | null>(null);
  // Même garde-fou que CheckoutView.submittingRef : empêche un double clic
  // de déclencher deux appels d'initiation en parallèle.
  const retryingRef = useRef(false);

  async function handleRetry() {
    if (retryingRef.current) return;
    retryingRef.current = true;
    setRetrying(true);
    setRetryError(null);

    try {
      const payment = isAuthenticated
        ? await initiatePaymentViaAccount(order.order_number, 'wave')
        : await initiatePayment(order.order_number, 'wave', guestPhone ? { phone: guestPhone } : {});

      if (!payment.checkout_url) {
        retryingRef.current = false;
        setRetrying(false);
        setRetryError("Le paiement n'a pas pu être réinitié. Veuillez réessayer.");
        return;
      }

      if (guestPhone) {
        window.sessionStorage.setItem(`${PAYMENT_PHONE_KEY_PREFIX}${order.order_number}`, guestPhone);
      }

      // Même navigation complète que CheckoutView après l'initiation
      // d'origine — checkout_url pointera un jour vers pay.wave.com.
      window.location.href = payment.checkout_url;
    } catch {
      retryingRef.current = false;
      setRetrying(false);
      setRetryError("Le paiement n'a pas pu être réinitié. Veuillez réessayer.");
    }
  }

  return (
    <>
      <h1 className="font-display text-xl font-bold text-ink">{landedOn === 'success' ? 'Paiement non confirmé' : 'Paiement échoué'}</h1>
      <p className="mt-3 text-sm text-ink-muted">
        Le paiement de la commande {order.order_number} n&apos;a pas encore été confirmé. Vous pouvez suivre son
        état ou réessayer votre paiement.
      </p>

      {retryError && <p className="mt-3 text-sm text-[color:var(--color-danger)]">{retryError}</p>}

      <div className="mt-6 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
        {order.payment_method === 'wave' && (
          <Button onClick={handleRetry} disabled={retrying} className="w-full sm:w-auto">
            {retrying ? 'Nouvelle tentative…' : 'Réessayer le paiement'}
          </Button>
        )}
        <Button href="/suivi-commande" variant="outline" className="w-full sm:w-auto">
          Suivre ma commande
        </Button>
      </div>
    </>
  );
}
