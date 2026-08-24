import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { getAuthTokenMock } = vi.hoisted(() => ({ getAuthTokenMock: vi.fn() }));
vi.mock('@/lib/auth/server', () => ({ getAuthToken: getAuthTokenMock }));

const { POST } = await import('./route');

function jsonResponse(body: unknown, status = 201): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

describe('POST /api/account/orders (internal proxy — never confused with Laravel API v1)', () => {
  beforeEach(() => {
    getAuthTokenMock.mockReset();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('attaches the Bearer token read from the cookie — the client never supplies it', async () => {
    getAuthTokenMock.mockResolvedValue('cookie-token-value');
    const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: { order_number: 'TC-1' } }));

    const request = new Request('http://localhost/api/account/orders', {
      method: 'POST',
      headers: { 'Idempotency-Key': 'idem-1' },
      body: JSON.stringify({ items: [] }),
    });

    await POST(request);

    const [, init] = fetchSpy.mock.calls[0];
    const headers = init?.headers as Record<string, string>;
    expect(headers.Authorization).toBe('Bearer cookie-token-value');
  });

  it('forwards the Idempotency-Key header unchanged', async () => {
    getAuthTokenMock.mockResolvedValue('token');
    const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: {} }));

    const request = new Request('http://localhost/api/account/orders', {
      method: 'POST',
      headers: { 'Idempotency-Key': 'my-idem-key' },
      body: '{}',
    });

    await POST(request);

    const [, init] = fetchSpy.mock.calls[0];
    const headers = init?.headers as Record<string, string>;
    expect(headers['Idempotency-Key']).toBe('my-idem-key');
  });

  it('omits Authorization when there is no session cookie, instead of sending a bogus Bearer value', async () => {
    getAuthTokenMock.mockResolvedValue(null);
    const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: {} }));

    const request = new Request('http://localhost/api/account/orders', { method: 'POST', body: '{}' });
    await POST(request);

    const [, init] = fetchSpy.mock.calls[0];
    const headers = init?.headers as Record<string, string>;
    expect(headers.Authorization).toBeUndefined();
  });

  it('relays Laravel’s status code and body verbatim (e.g. a 409 stock conflict)', async () => {
    getAuthTokenMock.mockResolvedValue('token');
    vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ message: 'Stock insuffisant.' }, 409));

    const request = new Request('http://localhost/api/account/orders', { method: 'POST', body: '{}' });
    const response = await POST(request);

    expect(response.status).toBe(409);
    const body = await response.json();
    expect(body.message).toBe('Stock insuffisant.');
  });

  it('never includes the token in the response body sent back to the browser', async () => {
    getAuthTokenMock.mockResolvedValue('super-secret-token');
    vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: { order_number: 'TC-1' } }));

    const request = new Request('http://localhost/api/account/orders', { method: 'POST', body: '{}' });
    const response = await POST(request);

    const text = await response.text();
    expect(text).not.toContain('super-secret-token');
  });
});
