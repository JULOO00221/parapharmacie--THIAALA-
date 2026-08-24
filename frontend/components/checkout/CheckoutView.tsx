'use client';

import { useRouter } from 'next/navigation';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { useCart } from '@/components/cart/CartProvider';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { ApiError } from '@/lib/api/client';
import { createOrder } from '@/lib/api/orders';
import type { CreateOrderPayload, DeliveryZone, Store } from '@/lib/api/types';
import { formatPrice } from '@/lib/utils/format';
import { OrderSummary } from './OrderSummary';

/** sessionStorage key prefix the success page reads from — see OrderSuccessView. */
const LAST_ORDER_KEY_PREFIX = 'tambacounda-cosmetix:order:';

type FulfillmentMode = 'pickup' | 'delivery';

function fieldError(errors: Record<string, string[]>, field: string): string | undefined {
  return errors[field]?.[0];
}

export function CheckoutView({ stores, deliveryZones }: { stores: Store[]; deliveryZones: DeliveryZone[] }) {
  const { items, subtotal, clearCart } = useCart();
  const router = useRouter();

  const [selectedStoreId, setSelectedStoreId] = useState<number | null>(stores[0]?.id ?? null);
  const store = stores.find((candidate) => candidate.id === selectedStoreId) ?? null;

  // La livraison n'est proposée que si au moins une zone active a pu être
  // chargée — sinon le checkout reste utilisable en retrait uniquement.
  const [fulfillmentMode, setFulfillmentMode] = useState<FulfillmentMode>('pickup');
  const [selectedZoneId, setSelectedZoneId] = useState<number | null>(null);
  const [deliveryAddress, setDeliveryAddress] = useState('');
  const selectedZone = deliveryZones.find((zone) => zone.id === selectedZoneId) ?? null;

  const [customerName, setCustomerName] = useState('');
  const [customerPhone, setCustomerPhone] = useState('');
  const [customerEmail, setCustomerEmail] = useState('');

  const [submitting, setSubmitting] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [generalError, setGeneralError] = useState<string | null>(null);

  const nameInputRef = useRef<HTMLInputElement>(null);
  const phoneInputRef = useRef<HTMLInputElement>(null);
  const addressInputRef = useRef<HTMLTextAreaElement>(null);

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

    const isPickup = fulfillmentMode === 'pickup';

    const payload: CreateOrderPayload = {
      items: items.map((item) => ({ product_id: item.productId, quantity: item.quantity })),
      store_id: store.id,
      customer_name: customerName.trim(),
      customer_phone: customerPhone.trim(),
      customer_email: customerEmail.trim() || undefined,
      is_pickup: isPickup,
      // Jamais de delivery_fee ici : Laravel le recalcule exclusivement
      // depuis la zone sélectionnée (delivery_zone_id), jamais depuis le
      // frontend — see CreateOrderPayload's own doc comment.
      ...(isPickup
        ? {}
        : {
            delivery_zone_id: selectedZoneId ?? undefined,
            delivery_address: deliveryAddress.trim() || undefined,
          }),
      // Le retrait se paie en boutique, la livraison se paie à la
      // réception — deux moyens réellement distincts de la whitelist
      // serveur (OrderService::PAYMENT_METHODS).
      payment_method: isPickup ? 'cash_in_store' : 'cash_on_delivery',
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
          } else if (errors.delivery_address) {
            addressInputRef.current?.focus();
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
  const zoneError = fieldError(fieldErrors, 'delivery_zone_id');
  const addressError = fieldError(fieldErrors, 'delivery_address');

  const deliveryFeeEstimate =
    fulfillmentMode === 'delivery' && selectedZone ? Number.parseFloat(selectedZone.fee) : 0;

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

          <section aria-labelledby="checkout-store-heading" className="rounded-2xl border border-border bg-surface-raised p-4 sm:p-6">
            <h2 id="checkout-store-heading" className="text-lg font-semibold text-ink">
              Boutique
            </h2>

            {/* Structuré en liste (même avec une seule boutique aujourd'hui)
                pour rester compatible avec plusieurs boutiques plus tard. */}
            <fieldset className="mt-4">
              <legend className="sr-only">Choisir la boutique</legend>
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
                  <span className="block text-sm font-medium text-ink">{candidate.name}</span>
                </label>
              ))}
            </fieldset>
          </section>

          <section aria-labelledby="checkout-fulfillment-heading" className="rounded-2xl border border-border bg-surface-raised p-4 sm:p-6">
            <h2 id="checkout-fulfillment-heading" className="text-lg font-semibold text-ink">
              Mode de réception
            </h2>

            <fieldset className="mt-4 space-y-3">
              <legend className="sr-only">Choisir le mode de réception</legend>

              <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-border p-4 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                <input
                  type="radio"
                  name="fulfillment_mode"
                  checked={fulfillmentMode === 'pickup'}
                  onChange={() => setFulfillmentMode('pickup')}
                  className="mt-1 h-4 w-4 accent-brand-600"
                />
                <span>
                  <span className="block text-sm font-semibold text-ink">Retrait en boutique</span>
                  <span className="mt-0.5 block text-sm text-ink-muted">Aucun frais de livraison.</span>
                </span>
              </label>

              {/* La livraison n'est proposée que si des zones actives ont
                  réellement été chargées depuis l'API — jamais de liste
                  codée en dur, et le retrait reste toujours disponible si
                  aucune zone n'est là. */}
              {deliveryZones.length > 0 && (
                <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-border p-4 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                  <input
                    type="radio"
                    name="fulfillment_mode"
                    checked={fulfillmentMode === 'delivery'}
                    onChange={() => setFulfillmentMode('delivery')}
                    className="mt-1 h-4 w-4 accent-brand-600"
                  />
                  <span>
                    <span className="block text-sm font-semibold text-ink">Livraison</span>
                    <span className="mt-0.5 block text-sm text-ink-muted">Choisissez votre zone ci-dessous.</span>
                  </span>
                </label>
              )}
            </fieldset>

            {fulfillmentMode === 'delivery' && (
              <div className="mt-4 space-y-4 border-t border-border pt-4">
                <fieldset>
                  <legend className="mb-2 block text-sm font-medium text-ink">
                    Zone de livraison <span aria-hidden="true">*</span>
                  </legend>
                  <div className="space-y-2">
                    {deliveryZones.map((zone) => (
                      <label
                        key={zone.id}
                        className="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-border p-3 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50"
                      >
                        <span className="flex items-center gap-3">
                          <input
                            type="radio"
                            name="delivery_zone_id"
                            required
                            checked={selectedZoneId === zone.id}
                            onChange={() => setSelectedZoneId(zone.id)}
                            aria-describedby={zoneError ? 'delivery_zone_id-error' : undefined}
                            className="h-4 w-4 accent-brand-600"
                          />
                          <span className="text-sm font-medium text-ink">{zone.name}</span>
                        </span>
                        <span className="text-sm font-medium text-ink">{formatPrice(zone.fee)}</span>
                      </label>
                    ))}
                  </div>
                  {zoneError && (
                    <p id="delivery_zone_id-error" className="mt-1 text-xs text-[color:var(--color-danger)]">
                      {zoneError}
                    </p>
                  )}
                </fieldset>

                <div>
                  <label htmlFor="delivery_address" className="mb-1.5 block text-sm font-medium text-ink">
                    Adresse de livraison <span aria-hidden="true">*</span>
                  </label>
                  <textarea
                    ref={addressInputRef}
                    id="delivery_address"
                    name="delivery_address"
                    required
                    rows={3}
                    value={deliveryAddress}
                    onChange={(event) => setDeliveryAddress(event.target.value)}
                    aria-invalid={addressError ? true : undefined}
                    aria-describedby={addressError ? 'delivery_address-error' : undefined}
                    className="w-full rounded-lg border border-border bg-surface-raised px-3 py-2.5 text-sm"
                    placeholder="Quartier, repère, numéro de porte…"
                  />
                  {addressError && (
                    <p id="delivery_address-error" className="mt-1 text-xs text-[color:var(--color-danger)]">
                      {addressError}
                    </p>
                  )}
                </div>
              </div>
            )}
          </section>

          <section aria-labelledby="checkout-payment-heading" className="rounded-2xl border border-border bg-surface-raised p-4 sm:p-6">
            <h2 id="checkout-payment-heading" className="text-lg font-semibold text-ink">
              Paiement
            </h2>
            <p className="mt-2 text-sm text-ink-muted">
              {fulfillmentMode === 'pickup'
                ? 'Paiement à la boutique, au moment du retrait.'
                : 'Paiement à la livraison, au moment de la réception.'}{' '}
              Aucun paiement en ligne n&apos;est requis pour valider cette commande.
            </p>
          </section>
        </div>

        <div className="w-full lg:w-96 lg:shrink-0">
          <OrderSummary items={items} subtotal={subtotal} deliveryFee={deliveryFeeEstimate} />

          <Button type="submit" size="lg" disabled={submitting} className="mt-4 w-full">
            {submitting ? 'Envoi en cours…' : 'Confirmer ma commande'}
          </Button>
        </div>
      </form>
    </div>
  );
}
