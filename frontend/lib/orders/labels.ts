import type { Order, OrderStatus, PaymentStatus } from '../api/types';

/** French display labels — mirrors Filament's OrderResource constants for consistency between back-office and storefront. */
export const STATUS_LABELS: Record<OrderStatus, string> = {
  pending: 'En attente',
  confirmed: 'Confirmée',
  preparing: 'En préparation',
  ready: 'Prête',
  delivered: 'Retirée / Livrée',
  cancelled: 'Annulée',
};

export const PAYMENT_STATUS_LABELS: Record<PaymentStatus, string> = {
  pending: 'En attente',
  paid: 'Payée',
};

export const PAYMENT_METHOD_LABELS: Record<Order['payment_method'], string> = {
  cash_in_store: 'Paiement à la boutique',
  cash_on_delivery: 'Paiement à la livraison',
};
