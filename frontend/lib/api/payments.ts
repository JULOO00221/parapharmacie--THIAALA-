import { apiGet, apiPost, request } from './client';
import type { InitiatePaymentPayload, Payment, PaymentProvider, SingleResponse } from './types';

/**
 * POST /orders/{orderNumber}/payments — guest path, direct to Laravel.
 * Same ownership rule as getOrder: a guest proves it via `phone`
 * (X-Order-Phone), never in the URL.
 */
export async function initiatePayment(
  orderNumber: string,
  provider: PaymentProvider,
  options: { phone?: string } = {}
): Promise<Payment> {
  const body: InitiatePaymentPayload = { provider };

  const response = await apiPost<SingleResponse<Payment>>(`/orders/${encodeURIComponent(orderNumber)}/payments`, body, {
    headers: options.phone ? { 'X-Order-Phone': options.phone } : undefined,
  });

  return response.data;
}

/**
 * Authenticated path: posts through the Next.js Route Handler at
 * /api/account/orders/{orderNumber}/payments (same-origin) so the server
 * can attach the Bearer token from the HttpOnly cookie — CheckoutView
 * never has access to it. Same pattern as createOrderViaAccount.
 */
export async function initiatePaymentViaAccount(orderNumber: string, provider: PaymentProvider): Promise<Payment> {
  const body: InitiatePaymentPayload = { provider };

  const response = await request<SingleResponse<Payment>>(
    `/api/account/orders/${encodeURIComponent(orderNumber)}/payments`,
    {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    },
    {}
  );

  return response.data;
}

/**
 * POST /payments/wave/mock/{transactionId}/simulate — dev/test-only mock
 * simulator, never the real Wave webhook. Laravel itself refuses this
 * outside mock mode / production (see MockWaveWebhookController), this
 * function is only ever called from the local /paiement/mock page.
 */
export async function simulateMockWavePayment(
  transactionId: string,
  outcome: 'succeeded' | 'failed' | 'expired'
): Promise<Payment> {
  const response = await apiPost<SingleResponse<Payment>>(`/payments/wave/mock/${encodeURIComponent(transactionId)}/simulate`, {
    outcome,
  });

  return response.data;
}

/**
 * GET /orders/{orderNumber}/payments/{transactionId}. Never trust this
 * (or any other client-side read) as proof of a successful payment on
 * its own merit beyond what Laravel actually returns — the payment
 * result pages re-fetch this fresh rather than reading a stale snapshot.
 */
export async function getPayment(
  orderNumber: string,
  transactionId: string,
  options: { phone?: string; token?: string } = {}
): Promise<Payment> {
  const headers: Record<string, string> = {};
  if (options.phone) headers['X-Order-Phone'] = options.phone;
  if (options.token) headers.Authorization = `Bearer ${options.token}`;

  const response = await apiGet<SingleResponse<Payment>>(
    `/orders/${encodeURIComponent(orderNumber)}/payments/${encodeURIComponent(transactionId)}`,
    { headers: Object.keys(headers).length > 0 ? headers : undefined }
  );

  return response.data;
}
