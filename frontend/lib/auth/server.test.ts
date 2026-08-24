import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from '../api/client';

const { apiMeMock, cookieState, store } = vi.hoisted(() => ({
  apiMeMock: vi.fn(),
  cookieState: { mutable: true },
  store: new Map<string, string>(),
}));

vi.mock('next/headers', () => ({
  cookies: vi.fn(async () => ({
    get: (name: string) => (store.has(name) ? { name, value: store.get(name) as string } : undefined),
    set: (name: string, value: string) => {
      if (!cookieState.mutable) {
        throw new Error('Cookies can only be modified in a Server Action or Route Handler');
      }
      store.set(name, value);
    },
    delete: (name: string) => {
      if (!cookieState.mutable) {
        throw new Error('Cookies can only be modified in a Server Action or Route Handler');
      }
      store.delete(name);
    },
  })),
}));

vi.mock('next/navigation', () => ({
  redirect: vi.fn((path: string) => {
    throw new Error(`NEXT_REDIRECT:${path}`);
  }),
}));

vi.mock('./api', () => ({ apiMe: (...args: unknown[]) => apiMeMock(...args) }));

// Static import, not a dynamic per-test one: server.ts's own static import
// of ApiError must resolve to the exact same class this file imports
// above, otherwise `error instanceof ApiError` inside getCurrentUser()
// silently fails (a real risk with vi.resetModules(), which was tried
// here first and produced exactly that failure — see git history if
// revisiting this).
const { clearAuthCookie, getAuthToken, getCurrentUser, requireUser, setAuthCookie } = await import('./server');

describe('lib/auth/server', () => {
  beforeEach(() => {
    store.clear();
    cookieState.mutable = true;
    apiMeMock.mockReset();
  });

  it('getAuthToken returns null when no cookie is set', async () => {
    expect(await getAuthToken()).toBeNull();
  });

  it('setAuthCookie then getAuthToken round-trips the token', async () => {
    await setAuthCookie('my-token');
    expect(await getAuthToken()).toBe('my-token');
  });

  it('clearAuthCookie removes the token', async () => {
    await setAuthCookie('my-token');
    await clearAuthCookie();
    expect(await getAuthToken()).toBeNull();
  });

  it('getCurrentUser returns null without calling /me when there is no cookie', async () => {
    const user = await getCurrentUser();
    expect(user).toBeNull();
    expect(apiMeMock).not.toHaveBeenCalled();
  });

  it('getCurrentUser returns the user on a valid token', async () => {
    apiMeMock.mockResolvedValue({ id: 1, name: 'Awa Diop', email: 'awa@example.com' });
    await setAuthCookie('valid-token');

    const user = await getCurrentUser();

    expect(user).toEqual({ id: 1, name: 'Awa Diop', email: 'awa@example.com' });
    expect(apiMeMock).toHaveBeenCalledWith('valid-token');
  });

  it('never treats the mere presence of a cookie as proof of authentication — a 401 from /me clears it and returns null', async () => {
    apiMeMock.mockRejectedValue(new ApiError(401, { message: 'Unauthenticated.' }));
    await setAuthCookie('revoked-token');

    const user = await getCurrentUser();

    expect(user).toBeNull();
    expect(await getAuthToken()).toBeNull();
  });

  it('does not crash when the cookie cannot be cleared from a read-only context (Server Component) — still returns null', async () => {
    apiMeMock.mockRejectedValue(new ApiError(401, { message: 'Unauthenticated.' }));
    await setAuthCookie('revoked-token');
    cookieState.mutable = false;

    const user = await getCurrentUser();

    expect(user).toBeNull();
  });

  it('a network failure from /me returns null without clearing the cookie', async () => {
    apiMeMock.mockRejectedValue(new ApiError(0, { message: 'network' }));
    await setAuthCookie('some-token');

    const user = await getCurrentUser();

    expect(user).toBeNull();
    expect(await getAuthToken()).toBe('some-token');
  });

  it('requireUser redirects to /connexion when there is no session', async () => {
    await expect(requireUser()).rejects.toThrow('NEXT_REDIRECT:/connexion');
  });

  it('requireUser returns the user and token when authenticated', async () => {
    apiMeMock.mockResolvedValue({ id: 1, name: 'Awa Diop', email: 'awa@example.com' });
    await setAuthCookie('valid-token');

    const result = await requireUser();

    expect(result.user.email).toBe('awa@example.com');
    expect(result.token).toBe('valid-token');
  });
});
