import { afterEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from './client';
import { createOrder, getOrder } from './orders';
import type { CreateOrderPayload, Order } from './types';

const VALID_PAYLOAD: CreateOrderPayload = {
  items: [{ product_id: 1, quantity: 2 }],
  store_id: 1,
  customer_name: 'Awa Diop',
  customer_phone: '+221771234567',
  customer_email: undefined,
  is_pickup: true,
  payment_method: 'cash_in_store',
};

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

const SAMPLE_ORDER: Order = {
  order_number: 'TC-20260824-ABCDEFGHIJ',
  status: 'pending',
  payment_status: 'pending',
  payment_method: 'cash_in_store',
  customer: { name: 'Awa Diop', phone: '+221771234567', email: null },
  store: { id: 1, name: 'Tambacounda Cosmetix' },
  delivery: { is_pickup: true, zone: null, address: null },
  notes: null,
  items: [{ product_id: 1, product_name: 'Savon noir', sku: 'TC-0001', quantity: 2, unit_price: '1000.00', subtotal: '2000.00' }],
  subtotal: '2000.00',
  delivery_fee: '0.00',
  total: '2000.00',
  currency: 'XOF',
  created_at: '2026-08-24T00:00:00.000000Z',
};

describe('lib/api/orders', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('createOrder', () => {
    it('sends only product_id and quantity per line item — never a price, subtotal, total, delivery_fee, stock or cost_price', async () => {
      const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: SAMPLE_ORDER }, 201));

      // Un appelant buggé pourrait construire un payload portant des champs
      // hors du type CreateOrderPayload (ex. via un spread depuis un état
      // local mal typé) — TypeScript seul ne bloque PAS ce cas précis
      // (l'excess-property check ne s'applique pas à un objet construit
      // via spread puis passé à une fonction), d'où le `as CreateOrderPayload`
      // explicite ci-dessous : on simule volontairement ce contournement
      // pour prouver que createOrder() les filtre quand même à l'exécution.
      const payloadWithForbiddenFields = {
        ...VALID_PAYLOAD,
        price: 1,
        unit_price: 1,
        subtotal: 1,
        delivery_fee: 1,
        total: 1,
        stock: 999,
        cost_price: 1,
      } as CreateOrderPayload;

      await createOrder(payloadWithForbiddenFields, 'idem-key-1234567890');

      const [, init] = fetchSpy.mock.calls[0];
      const sentBody = JSON.parse(init?.body as string);

      expect(sentBody).toEqual({
        items: [{ product_id: 1, quantity: 2 }],
        store_id: 1,
        customer_name: 'Awa Diop',
        customer_phone: '+221771234567',
        customer_email: undefined,
        is_pickup: true,
        payment_method: 'cash_in_store',
      });

      for (const forbidden of ['price', 'unit_price', 'subtotal', 'delivery_fee', 'total', 'stock', 'cost_price']) {
        expect(sentBody).not.toHaveProperty(forbidden);
      }
    });

    it('sends the Idempotency-Key header exactly as given', async () => {
      const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: SAMPLE_ORDER }, 201));

      await createOrder(VALID_PAYLOAD, 'my-unique-key-42');

      const [, init] = fetchSpy.mock.calls[0];
      const headers = init?.headers as Record<string, string>;
      expect(headers['Idempotency-Key']).toBe('my-unique-key-42');
    });

    it('resolves with the created order on success', async () => {
      vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: SAMPLE_ORDER }, 201));

      const order = await createOrder(VALID_PAYLOAD, 'key');

      expect(order.order_number).toBe(SAMPLE_ORDER.order_number);
      expect(order.total).toBe('2000.00');
    });

    it('throws an ApiError(422) with field errors on validation failure', async () => {
      vi.spyOn(global, 'fetch').mockResolvedValue(
        jsonResponse({ message: 'Validation failed.', errors: { customer_phone: ['Le téléphone est obligatoire.'] } }, 422)
      );

      const error = await createOrder(VALID_PAYLOAD, 'key').catch((e) => e);

      expect(error).toBeInstanceOf(ApiError);
      expect((error as ApiError).status).toBe(422);
      expect((error as ApiError).payload.errors?.customer_phone).toEqual(['Le téléphone est obligatoire.']);
    });

    it('throws an ApiError(409) on insufficient stock', async () => {
      vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ message: 'Stock insuffisant.' }, 409));

      const error = await createOrder(VALID_PAYLOAD, 'key').catch((e) => e);

      expect(error).toBeInstanceOf(ApiError);
      expect((error as ApiError).status).toBe(409);
    });

    it('throws an ApiError(429) when rate limited', async () => {
      vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ message: 'Too Many Attempts.' }, 429));

      const error = await createOrder(VALID_PAYLOAD, 'key').catch((e) => e);

      expect(error).toBeInstanceOf(ApiError);
      expect((error as ApiError).status).toBe(429);
    });

    it('throws an ApiError(0) on a network failure, distinguishable from an HTTP error', async () => {
      vi.spyOn(global, 'fetch').mockRejectedValue(new TypeError('Failed to fetch'));

      const error = await createOrder(VALID_PAYLOAD, 'key').catch((e) => e);

      expect(error).toBeInstanceOf(ApiError);
      expect((error as ApiError).status).toBe(0);
    });
  });

  describe('getOrder', () => {
    it('sends X-Order-Phone when a phone is supplied (guest lookup)', async () => {
      const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: SAMPLE_ORDER }));

      await getOrder(SAMPLE_ORDER.order_number, { phone: '+221771234567' });

      const [, init] = fetchSpy.mock.calls[0];
      const headers = init?.headers as Record<string, string>;
      expect(headers['X-Order-Phone']).toBe('+221771234567');
    });

    it('omits X-Order-Phone when no phone is given (authenticated lookup)', async () => {
      const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: SAMPLE_ORDER }));

      await getOrder(SAMPLE_ORDER.order_number);

      const [, init] = fetchSpy.mock.calls[0];
      const headers = init?.headers as Record<string, string>;
      expect(headers['X-Order-Phone']).toBeUndefined();
    });

    it('never puts the phone number in the URL', async () => {
      const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: SAMPLE_ORDER }));

      await getOrder(SAMPLE_ORDER.order_number, { phone: '+221771234567' });

      const [url] = fetchSpy.mock.calls[0];
      expect(String(url)).not.toContain('221771234567');
    });
  });
});
