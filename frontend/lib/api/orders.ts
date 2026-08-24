import { apiGet, apiPost, request } from './client';
import type { CreateOrderPayload, Order, PaginatedResponse, SingleResponse } from './types';

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
 * number via `X-Order-Phone` — never in the URL. Pass `phone` for a guest
 * lookup (/suivi-commande) or `token` for an authenticated one
 * (/compte/commandes/[orderNumber]) — never both from the same caller.
 * Laravel returns 404 (never 403) when the combination doesn't match, so
 * a wrong guess can never confirm another customer's order exists.
 */
export async function getOrder(orderNumber: string, options: { phone?: string; token?: string } = {}): Promise<Order> {
  const headers: Record<string, string> = {};
  if (options.phone) headers['X-Order-Phone'] = options.phone;
  if (options.token) headers.Authorization = `Bearer ${options.token}`;

  const response = await apiGet<SingleResponse<Order>>(`/orders/${encodeURIComponent(orderNumber)}`, {
    headers: Object.keys(headers).length > 0 ? headers : undefined,
  });

  return response.data;
}

/**
 * GET /orders — authenticated customer's own order history, always
 * scoped server-side by Laravel to the token's owner (never a filterable
 * user_id). `token` is read from the HttpOnly cookie by a Server
 * Component (see lib/auth/server.ts) and is never seen by client code.
 */
export async function getMyOrders(
  token: string,
  params: { page?: number; per_page?: number } = {}
): Promise<PaginatedResponse<Order>> {
  return apiGet<PaginatedResponse<Order>>('/orders', {
    params,
    headers: { Authorization: `Bearer ${token}` },
  });
}

/**
 * Authenticated checkout: posts through the Next.js Route Handler at
 * /api/account/orders (same-origin) instead of directly to Laravel, so
 * the server can attach the Bearer token from the HttpOnly cookie —
 * CheckoutView never has access to it. The guest path (createOrder
 * above) is untouched and keeps hitting Laravel directly.
 */
export async function createOrderViaAccount(payload: CreateOrderPayload, idempotencyKey: string): Promise<Order> {
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

  const response = await request<SingleResponse<Order>>(
    '/api/account/orders',
    {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'Idempotency-Key': idempotencyKey },
      body: JSON.stringify(body),
    },
    {}
  );

  return response.data;
}
