import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { revalidateTagMock } = vi.hoisted(() => ({ revalidateTagMock: vi.fn() }));
vi.mock('next/cache', () => ({ revalidateTag: revalidateTagMock }));

const { POST } = await import('./route');

const SECRET = 'a'.repeat(40);

function post(authorization?: string): Promise<Response> {
  return POST(
    new Request('http://localhost/api/revalidate', {
      method: 'POST',
      headers: authorization ? { Authorization: authorization } : {},
    }),
  );
}

describe('POST /api/revalidate', () => {
  beforeEach(() => {
    revalidateTagMock.mockReset();
    vi.spyOn(console, 'error').mockImplementation(() => {});
  });

  afterEach(() => {
    vi.unstubAllEnvs();
    vi.restoreAllMocks();
  });

  it('expires the catalog tag immediately with the right secret', async () => {
    vi.stubEnv('REVALIDATE_SECRET', SECRET);

    const response = await post(`Bearer ${SECRET}`);

    expect(response.status).toBe(200);
    expect(revalidateTagMock).toHaveBeenCalledWith('catalog', { expire: 0 });
    await expect(response.json()).resolves.toMatchObject({ revalidated: true, tag: 'catalog' });
  });

  it.each([
    ['no header', undefined],
    ['a wrong secret', `Bearer ${'b'.repeat(40)}`],
    ['a secret of another length', 'Bearer short'],
    ['another scheme', `Basic ${SECRET}`],
  ])('rejects %s with 401', async (_label, authorization) => {
    vi.stubEnv('REVALIDATE_SECRET', SECRET);

    const response = await post(authorization);

    expect(response.status).toBe(401);
    expect(revalidateTagMock).not.toHaveBeenCalled();
  });

  it('refuses everything when the secret is missing or too short, instead of staying open', async () => {
    for (const secret of ['', 'too-short']) {
      vi.stubEnv('REVALIDATE_SECRET', secret);

      const response = await post(`Bearer ${secret}`);

      expect(response.status).toBe(503);
    }
    expect(revalidateTagMock).not.toHaveBeenCalled();
  });
});
