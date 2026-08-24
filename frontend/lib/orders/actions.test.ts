import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from '../api/client';

const { getOrderMock } = vi.hoisted(() => ({ getOrderMock: vi.fn() }));
vi.mock('../api/orders', () => ({ getOrder: getOrderMock }));

const { trackOrderAction } = await import('./actions');

function formData(fields: Record<string, string>): FormData {
  const data = new FormData();
  for (const [key, value] of Object.entries(fields)) data.set(key, value);
  return data;
}

const SAMPLE_ORDER = {
  order_number: 'TC-20260824-ABCDEFGHIJ',
  status: 'pending',
  payment_status: 'pending',
  payment_method: 'cash_in_store',
  customer: { name: 'Awa Diop', phone: '+221771234567', email: null },
  store: { id: 1, name: 'Tambacounda Cosmetix' },
  delivery: { is_pickup: true, zone: null, address: null },
  notes: null,
  items: [],
  subtotal: '2000.00',
  delivery_fee: '0.00',
  total: '2000.00',
  currency: 'XOF',
  created_at: '2026-08-24T00:00:00.000000Z',
};

describe('lib/orders/actions — trackOrderAction', () => {
  beforeEach(() => {
    getOrderMock.mockReset();
  });

  it('rejects without calling the API when a field is missing', async () => {
    const state = await trackOrderAction({ status: 'idle' }, formData({ order_number: '', phone: '' }));

    expect(state.status).toBe('error');
    expect(getOrderMock).not.toHaveBeenCalled();
  });

  it('returns the order and never sends the phone in the URL (delegated to getOrder, already covered) when the combination matches', async () => {
    getOrderMock.mockResolvedValue(SAMPLE_ORDER);

    const state = await trackOrderAction(
      { status: 'idle' },
      formData({ order_number: 'TC-20260824-ABCDEFGHIJ', phone: '+221771234567' })
    );

    expect(state).toEqual({ status: 'found', order: SAMPLE_ORDER });
    expect(getOrderMock).toHaveBeenCalledWith('TC-20260824-ABCDEFGHIJ', { phone: '+221771234567' });
  });

  it('returns a generic message on a wrong order/phone combination — never reveals whether the order exists', async () => {
    getOrderMock.mockRejectedValue(new ApiError(404, { message: 'Not Found.' }));

    const state = await trackOrderAction(
      { status: 'idle' },
      formData({ order_number: 'TC-20260824-WRONGWRONG', phone: '+221700000000' })
    );

    expect(state.status).toBe('error');
    if (state.status === 'error') {
      expect(state.message).not.toMatch(/existe|found|trouvée avec un autre/i);
      expect(state.message).toMatch(/aucune commande trouvée/i);
    }
  });

  it('returns the same generic message shape for a non-404 failure (never a different, more revealing message)', async () => {
    getOrderMock.mockRejectedValue(new ApiError(0, { message: 'network down' }));

    const state = await trackOrderAction(
      { status: 'idle' },
      formData({ order_number: 'TC-20260824-ABCDEFGHIJ', phone: '+221771234567' })
    );

    expect(state.status).toBe('error');
  });
});
