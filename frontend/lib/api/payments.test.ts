import { afterEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from './client';
import { getPayment, initiatePayment, initiatePaymentViaAccount, simulateMockWavePayment } from './payments';
import type { Payment } from './types';

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

const SAMPLE_PAYMENT: Payment = {
  transaction_id: 'PAY-20260825-ABCDEFGHIJ',
  provider: 'wave',
  status: 'processing',
  amount: '4500.00',
  currency: 'XOF',
  checkout_url: 'http://localhost:3000/paiement/mock/PAY-20260825-ABCDEFGHIJ?order=TC-20260825-XXXXXXXXXX',
  failure_reason: null,
  expires_at: '2026-08-25T00:15:00.000000Z',
  paid_at: null,
  created_at: '2026-08-25T00:00:00.000000Z',
};

describe('lib/api/payments', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('initiatePayment (guest)', () => {
    it('sends only {provider} — never an amount, currency, or total supplied by the frontend', async () => {
      const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: SAMPLE_PAYMENT }, 201));

      await initiatePayment('TC-20260825-XXXXXXXXXX', 'wave', { phone: '+221771234567' });

      const [, init] = fetchSpy.mock.calls[0];
      const sentBody = JSON.parse(init?.body as string);
      expect(sentBody).toEqual({ provider: 'wave' });
      expect(sentBody).not.toHaveProperty('amount');
      expect(sentBody).not.toHaveProperty('total');
      expect(sentBody).not.toHaveProperty('currency');
    });

    it('sends X-Order-Phone for a guest and never puts the phone in the URL', async () => {
      const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: SAMPLE_PAYMENT }, 201));

      await initiatePayment('TC-20260825-XXXXXXXXXX', 'wave', { phone: '+221771234567' });

      const [url, init] = fetchSpy.mock.calls[0];
      const headers = init?.headers as Record<string, string>;
      expect(headers['X-Order-Phone']).toBe('+221771234567');
      expect(String(url)).not.toContain('221771234567');
    });

    it('resolves with a processing payment carrying a checkout_url', async () => {
      vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: SAMPLE_PAYMENT }, 201));

      const payment = await initiatePayment('TC-20260825-XXXXXXXXXX', 'wave', { phone: '+221771234567' });

      expect(payment.status).toBe('processing');
      expect(payment.checkout_url).toContain('/paiement/mock/');
    });

    it('throws an ApiError(404) when the guest phone does not match the order', async () => {
      vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ message: 'Not Found.' }, 404));

      const error = await initiatePayment('TC-20260825-XXXXXXXXXX', 'wave', { phone: '+221700000000' }).catch((e) => e);

      expect(error).toBeInstanceOf(ApiError);
      expect((error as ApiError).status).toBe(404);
    });

    it('throws an ApiError(409) when the order cannot be paid anymore (e.g. already confirmed/cancelled)', async () => {
      vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ message: 'Cette commande ne peut plus être payée.' }, 409));

      const error = await initiatePayment('TC-20260825-XXXXXXXXXX', 'wave', { phone: '+221771234567' }).catch((e) => e);

      expect(error).toBeInstanceOf(ApiError);
      expect((error as ApiError).status).toBe(409);
    });

    it('throws an ApiError(0) on a network failure, distinguishable from an HTTP error', async () => {
      vi.spyOn(global, 'fetch').mockRejectedValue(new TypeError('Failed to fetch'));

      const error = await initiatePayment('TC-20260825-XXXXXXXXXX', 'wave', { phone: '+221771234567' }).catch((e) => e);

      expect(error).toBeInstanceOf(ApiError);
      expect((error as ApiError).status).toBe(0);
    });
  });

  describe('initiatePaymentViaAccount (authenticated proxy)', () => {
    it('posts to the internal /api/account proxy — never directly to the Laravel API', async () => {
      const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: SAMPLE_PAYMENT }, 201));

      await initiatePaymentViaAccount('TC-20260825-XXXXXXXXXX', 'wave');

      const [url, init] = fetchSpy.mock.calls[0];
      expect(String(url)).toContain('/api/account/orders/TC-20260825-XXXXXXXXXX/payments');
      expect(String(url)).not.toContain('/api/v1');
      const sentBody = JSON.parse(init?.body as string);
      expect(sentBody).toEqual({ provider: 'wave' });
    });
  });

  describe('getPayment', () => {
    it('sends the token as a Bearer header for an authenticated lookup, never in the URL', async () => {
      const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: SAMPLE_PAYMENT }));

      await getPayment('TC-20260825-XXXXXXXXXX', SAMPLE_PAYMENT.transaction_id, { token: 'secret-token' });

      const [url, init] = fetchSpy.mock.calls[0];
      const headers = init?.headers as Record<string, string>;
      expect(headers.Authorization).toBe('Bearer secret-token');
      expect(String(url)).not.toContain('secret-token');
    });

    it('sends X-Order-Phone for a guest lookup', async () => {
      const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: SAMPLE_PAYMENT }));

      await getPayment('TC-20260825-XXXXXXXXXX', SAMPLE_PAYMENT.transaction_id, { phone: '+221771234567' });

      const [, init] = fetchSpy.mock.calls[0];
      const headers = init?.headers as Record<string, string>;
      expect(headers['X-Order-Phone']).toBe('+221771234567');
    });
  });

  describe('simulateMockWavePayment', () => {
    it('posts the outcome to the dev-only mock endpoint, never the real callback path', async () => {
      const paid: Payment = { ...SAMPLE_PAYMENT, status: 'paid', checkout_url: null, paid_at: '2026-08-25T00:05:00.000000Z' };
      const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: paid }));

      const result = await simulateMockWavePayment(SAMPLE_PAYMENT.transaction_id, 'succeeded');

      const [url, init] = fetchSpy.mock.calls[0];
      expect(String(url)).toContain('/payments/wave/mock/');
      expect(String(url)).toContain('/simulate');
      expect(String(url)).not.toContain('/payments/wave/callback');
      expect(JSON.parse(init?.body as string)).toEqual({ outcome: 'succeeded' });
      expect(result.status).toBe('paid');
    });

    it('throws an ApiError(404) if the mock endpoint is unavailable (e.g. disabled in production)', async () => {
      vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ message: 'Not Found.' }, 404));

      const error = await simulateMockWavePayment(SAMPLE_PAYMENT.transaction_id, 'succeeded').catch((e) => e);

      expect(error).toBeInstanceOf(ApiError);
      expect((error as ApiError).status).toBe(404);
    });
  });
});
