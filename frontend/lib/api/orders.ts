import { apiGet, apiPost } from './client';
import type { CreateOrderPayload, Order, SingleResponse } from './types';

/**
 * POST /orders. `idempotencyKey` is required by the API (rejected with a
 * 422 otherwise) — the caller owns generating and reusing it across
 * retries of the *same* logical submission, this function never invents
 * one itself.
 *
 * The request body is rebuilt field by field rather than serializing
 * `payload` as-is: TypeScript's excess-property check only applies to
 * object literals assigned directly to a typed slot, not to an object
 * built elsewhere and passed in — so on its own, CreateOrderPayload's
 * type would NOT stop a stray `price`/`total`/`stock` field from actually
 * being sent if a future caller's payload carried one. This makes the
 * "never send a price/total/stock field" rule a real runtime guarantee.
 */
export async function createOrder(payload: CreateOrderPayload, idempotencyKey: string): Promise<Order> {
  const body: CreateOrderPayload = {
    items: payload.items.map((item) => ({ product_id: item.product_id, quantity: item.quantity })),
    store_id: payload.store_id,
    customer_name: payload.customer_name,
    customer_phone: payload.customer_phone,
    customer_email: payload.customer_email,
    is_pickup: payload.is_pickup,
    delivery_zone_id: payload.delivery_zone_id,
    delivery_address: payload.delivery_address,
    notes: payload.notes,
    payment_method: payload.payment_method,
  };

  const response = await apiPost<SingleResponse<Order>>('/orders', body, {
    headers: { 'Idempotency-Key': idempotencyKey },
  });

  return response.data;
}

/**
 * GET /orders/{orderNumber}. Laravel only reveals the order to its
 * authenticated owner or to whoever supplies the exact checkout phone
 * number via `X-Order-Phone` — never in the URL. Omit `phone` when
 * relying on an authenticated session instead.
 */
export async function getOrder(orderNumber: string, options: { phone?: string } = {}): Promise<Order> {
  const response = await apiGet<SingleResponse<Order>>(`/orders/${encodeURIComponent(orderNumber)}`, {
    headers: options.phone ? { 'X-Order-Phone': options.phone } : undefined,
  });

  return response.data;
}
