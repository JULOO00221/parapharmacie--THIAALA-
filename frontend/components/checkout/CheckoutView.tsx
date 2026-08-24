'use client';

import { useRouter } from 'next/navigation';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { useCart } from '@/components/cart/CartProvider';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { ApiError } from '@/lib/api/client';
import { createOrder } from '@/lib/api/orders';
import type { CreateOrderPayload, Store } from '@/lib/api/types';
import { OrderSummary } from './OrderSummary';

/** sessionStorage key prefix the success page reads from — see OrderSuccessView. */
const LAST_ORDER_KEY_PREFIX = 'tambacounda-cosmetix:order:';

function fieldError(errors: Record<string, string[]>, field: string): string | undefined {
  return errors[field]?.[0];
}

export function CheckoutView({ stores }: { stores: Store[] }) {
  const { items, subtotal, clearCart } = useCart();
  const router = useRouter();

  const [selectedStoreId, setSelectedStoreId] = useState<number | null>(stores[0]?.id ?? null);
  const store = stores.find((candidate) => candidate.id === selectedStoreId) ?? null;

  const [customerName, setCustomerName] = useState('');
  const [customerPhone, setCustomerPhone] = useState('');
  const [customerEmail, setCustomerEmail] = useState('');

  const [submitting, setSubmitting] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [generalError, setGeneralError] = useState<string | null>(null);

  const nameInputRef = useRef<HTMLInputElement>(null);
  const phoneInputRef = useRef<HTMLInputElement>(null);

  // Générée une seule fois (initialiseur paresseux de useState), jamais
  // recréée à chaque re-render, et réutilisée pour toute nouvelle
  // tentative de CETTE soumission logique (ex. après un échec réseau) —
  // c'est exactement ce qui permet à Laravel de reconnaître un doublon.
  const [idempotencyKey] = useState(() => crypto.randomUUID());

  if (items.length === 0) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <EmptyState
          title="Votre panier est vide"
          description="Ajoutez des produits avant de passer commande."
          action={<Button href="/produits">Voir les produits</Button>}
        />
      </div>
    );
  }

  if (!store) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <EmptyState
          title="Aucune boutique disponible pour le moment"
          description="Merci de réessayer un peu plus tard."
          action={<Button href="/panier">Retour au panier</Button>}
        />
      </div>
    );
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (submitting || !store) return;

    setSubmitting(true);
    setFieldErrors({});
    setGeneralError(null);

    const payload: CreateOrderPayload = {
      items: items.map((item) => ({ product_id: item.productId, quantity: item.quantity })),
      store_id: store.id,
      customer_name: customerName.trim(),
      customer_phone: customerPhone.trim(),
      customer_email: customerEmail.trim() || undefined,
      is_pickup: true,
      payment_method: 'cash_in_store',
    };

    try {
      const order = await createOrder(payload, idempotencyKey);

      // Stocké côté client uniquement pour que la page de succès affiche
      // instantanément les montants réellement renvoyés par Laravel, sans
      // nouvel appel — jamais utilisé comme source de vérité ailleurs.
      window.sessionStorage.setItem(`${LAST_ORDER_KEY_PREFIX}${order.order_number}`, JSON.stringify(order));

      // Le panier n'est vidé qu'ICI, après le succès HTTP 201 réel —
      // jamais avant, jamais de façon optimiste.
      clearCart();
      router.push(`/commande/succes/${order.order_number}`);
    } catch (error) {
      setSubmitting(false);

      if (error instanceof ApiError) {
        if (error.status === 422) {
          const errors = error.payload.errors ?? {};
          setFieldErrors(errors);
          setGeneralError('Veuillez corriger les champs indiqués ci-dessous.');

          if (errors.customer_name) {
            nameInputRef.current?.focus();
          } else if (errors.customer_phone) {
            phoneInputRef.current?.focus();
          }
        } else if (error.status === 409) {
          setGeneralError(
            "Le stock d'un ou plusieurs produits n'est plus disponible. Vérifiez votre panier avant de réessayer."
          );
        } else if (error.status === 404) {
          setGeneralError('Un des produits ou ressources de votre commande est introuvable.');
        } else if (error.status === 429) {
          setGeneralError('Trop de tentatives — merci de patienter quelques instants avant de réessayer.');
        } else if (error.status === 0) {
          setGeneralError('Impossible de contacter le serveur. Vérifiez votre connexion, puis réessayez.');
        } else {
          setGeneralError('Une erreur est survenue. Veuillez réessayer.');
        }

        return;
      }

      setGeneralError('Une erreur est survenue. Veuillez réessayer.');
    }
  }

  const nameError = fieldError(fieldErrors, 'customer_name');
  const phoneError = fieldError(fieldErrors, 'customer_phone');
  const emailError = fieldError(fieldErrors, 'customer_email');

  return (
    <div className="mx-auto max-w-5xl px-4 py-10 sm:px-6">
      <h1 className="text-2xl font-bold text-ink sm:text-3xl">Finaliser ma commande</h1>

      <form onSubmit={handleSubmit} noValidate className="mt-8 flex flex-col gap-8 lg:flex-row lg:items-start">
        <div className="flex-1 space-y-8">
          {generalError && (
            <div role="alert" className="rounded-xl border border-[color:var(--color-danger)]/30 bg-red-50 px-4 py-3 text-sm text-[color:var(--color-danger)]">
              {generalError}
            </div>
          )}

          <section aria-labelledby="checkout-customer-heading" className="rounded-2xl border border-border bg-surface-raised p-4 sm:p-6">
            <h2 id="checkout-customer-heading" className="text-lg font-semibold text-ink">
              Vos coordonnées
            </h2>

            <div className="mt-4 space-y-4">
              <div>
                <label htmlFor="customer_name" className="mb-1.5 block text-sm font-medium text-ink">
                  Nom complet <span aria-hidden="true">*</span>
                </label>
                <input
                  ref={nameInputRef}
                  id="customer_name"
                  name="customer_name"
                  type="text"
                  required
                  autoComplete="name"
                  value={customerName}
                  onChange={(event) => setCustomerName(event.target.value)}
                  aria-invalid={nameError ? true : undefined}
                  aria-describedby={nameError ? 'customer_name-error' : undefined}
                  className="w-full rounded-lg border border-border bg-surface-raised px-3 py-2.5 text-sm"
                />
                {nameError && (
                  <p id="customer_name-error" className="mt-1 text-xs text-[color:var(--color-danger)]">
                    {nameError}
                  </p>
                )}
              </div>

              <div>
                <label htmlFor="customer_phone" className="mb-1.5 block text-sm font-medium text-ink">
                  Téléphone <span aria-hidden="true">*</span>
                </label>
                <input
                  ref={phoneInputRef}
                  id="customer_phone"
                  name="customer_phone"
                  type="tel"
                  required
                  autoComplete="tel"
                  value={customerPhone}
                  onChange={(event) => setCustomerPhone(event.target.value)}
                  aria-invalid={phoneError ? true : undefined}
                  aria-describedby={phoneError ? 'customer_phone-error' : undefined}
                  className="w-full rounded-lg border border-border bg-surface-raised px-3 py-2.5 text-sm"
                />
                {phoneError && (
                  <p id="customer_phone-error" className="mt-1 text-xs text-[color:var(--color-danger)]">
                    {phoneError}
                  </p>
                )}
                <p className="mt-1 text-xs text-ink-muted">
                  Utilisé pour vous contacter et pour retrouver votre commande.
                </p>
              </div>

              <div>
                <label htmlFor="customer_email" className="mb-1.5 block text-sm font-medium text-ink">
                  Email <span className="text-ink-muted">(facultatif)</span>
                </label>
                <input
                  id="customer_email"
                  name="customer_email"
                  type="email"
                  autoComplete="email"
                  value={customerEmail}
                  onChange={(event) => setCustomerEmail(event.target.value)}
                  aria-invalid={emailError ? true : undefined}
                  aria-describedby={emailError ? 'customer_email-error' : undefined}
                  className="w-full rounded-lg border border-border bg-surface-raised px-3 py-2.5 text-sm"
                />
                {emailError && (
                  <p id="customer_email-error" className="mt-1 text-xs text-[color:var(--color-danger)]">
                    {emailError}
                  </p>
                )}
              </div>
            </div>
          </section>

          <section aria-labelledby="checkout-fulfillment-heading" className="rounded-2xl border border-border bg-surface-raised p-4 sm:p-6">
            <h2 id="checkout-fulfillment-heading" className="text-lg font-semibold text-ink">
              Mode de réception
            </h2>

            {/* La livraison n'est pas encore proposée : aucune zone de
                livraison réelle n'existe actuellement. Structuré en liste
                de boutiques (même avec une seule aujourd'hui) pour rester
                compatible avec plusieurs boutiques plus tard. */}
            <fieldset className="mt-4">
              <legend className="sr-only">Choisir la boutique de retrait</legend>
              {stores.map((candidate) => (
                <label
                  key={candidate.id}
                  className="flex cursor-pointer items-start gap-3 rounded-xl border border-brand-600 bg-brand-50 p-4"
                >
                  <input
                    type="radio"
                    name="store_id"
                    value={candidate.id}
                    checked={selectedStoreId === candidate.id}
                    onChange={() => setSelectedStoreId(candidate.id)}
                    className="mt-1 h-4 w-4 accent-brand-600"
                  />
                  <span>
                    <span className="block text-sm font-semibold text-ink">Retrait en boutique</span>
                    <span className="mt-0.5 block text-sm text-ink-muted">{candidate.name}</span>
                  </span>
                </label>
              ))}
            </fieldset>
          </section>

          <section aria-labelledby="checkout-payment-heading" className="rounded-2xl border border-border bg-surface-raised p-4 sm:p-6">
            <h2 id="checkout-payment-heading" className="text-lg font-semibold text-ink">
              Paiement
            </h2>
            <p className="mt-2 text-sm text-ink-muted">
              Paiement à la boutique, au moment du retrait. Aucun paiement en ligne n&apos;est requis pour valider cette commande.
            </p>
          </section>
        </div>

        <div className="w-full lg:w-96 lg:shrink-0">
          <OrderSummary items={items} subtotal={subtotal} />

          <Button type="submit" size="lg" disabled={submitting} className="mt-4 w-full">
            {submitting ? 'Envoi en cours…' : 'Confirmer ma commande'}
          </Button>
        </div>
      </form>
    </div>
  );
}
