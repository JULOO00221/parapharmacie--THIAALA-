import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from '../api/client';

const { apiLoginMock, apiLogoutMock, apiRegisterMock, clearAuthCookieMock, getAuthTokenMock, redirectMock, setAuthCookieMock } =
  vi.hoisted(() => ({
    apiLoginMock: vi.fn(),
    apiLogoutMock: vi.fn(),
    apiRegisterMock: vi.fn(),
    clearAuthCookieMock: vi.fn(),
    getAuthTokenMock: vi.fn(),
    redirectMock: vi.fn((path: string) => {
      throw new Error(`NEXT_REDIRECT:${path}`);
    }),
    setAuthCookieMock: vi.fn(),
  }));

vi.mock('next/navigation', () => ({ redirect: redirectMock }));
vi.mock('./api', () => ({
  apiLogin: apiLoginMock,
  apiLogout: apiLogoutMock,
  apiRegister: apiRegisterMock,
}));
vi.mock('./server', () => ({
  clearAuthCookie: clearAuthCookieMock,
  getAuthToken: getAuthTokenMock,
  setAuthCookie: setAuthCookieMock,
}));

const { loginAction, logoutAction, registerAction } = await import('./actions');

function formData(fields: Record<string, string>): FormData {
  const data = new FormData();
  for (const [key, value] of Object.entries(fields)) data.set(key, value);
  return data;
}

describe('lib/auth/actions', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    redirectMock.mockImplementation((path: string) => {
      throw new Error(`NEXT_REDIRECT:${path}`);
    });
  });

  describe('loginAction', () => {
    it('returns an error without calling the API when a field is missing', async () => {
      const state = await loginAction({ error: null }, formData({ email: '', password: '' }));

      expect(state.error).toBeTruthy();
      expect(apiLoginMock).not.toHaveBeenCalled();
    });

    it('on success, sets the cookie and redirects to /compte — the returned state never carries the token', async () => {
      apiLoginMock.mockResolvedValue({ user: { id: 1, name: 'Awa', email: 'awa@example.com' }, token: 'secret-token' });

      await expect(loginAction({ error: null }, formData({ email: 'awa@example.com', password: 'x' }))).rejects.toThrow(
        'NEXT_REDIRECT:/compte'
      );

      expect(setAuthCookieMock).toHaveBeenCalledWith('secret-token');
    });

    it('maps a 422 to a generic "identifiants invalides" message', async () => {
      apiLoginMock.mockRejectedValue(new ApiError(422, { message: 'Validation failed.', errors: { email: ['x'] } }));

      const state = await loginAction({ error: null }, formData({ email: 'a@b.com', password: 'wrong' }));

      expect(state.error).toBe('Identifiants invalides.');
      expect(setAuthCookieMock).not.toHaveBeenCalled();
    });

    it('maps a 429 to a throttle message', async () => {
      apiLoginMock.mockRejectedValue(new ApiError(429, { message: 'Too Many Attempts.' }));

      const state = await loginAction({ error: null }, formData({ email: 'a@b.com', password: 'x' }));

      expect(state.error).toMatch(/tentatives/i);
    });

    it('maps a network failure (status 0) to a server-unreachable message', async () => {
      apiLoginMock.mockRejectedValue(new ApiError(0, { message: 'network' }));

      const state = await loginAction({ error: null }, formData({ email: 'a@b.com', password: 'x' }));

      expect(state.error).toMatch(/serveur|connexion/i);
    });

    it('never returns a token field on any outcome', async () => {
      apiLoginMock.mockRejectedValue(new ApiError(422, { message: 'x' }));
      const state = await loginAction({ error: null }, formData({ email: 'a@b.com', password: 'x' }));
      expect(state).not.toHaveProperty('token');
    });
  });

  describe('registerAction', () => {
    it('returns an error without calling the API when a field is missing', async () => {
      const state = await registerAction({ error: null }, formData({ name: '', email: '', password: '', password_confirmation: '' }));

      expect(state.error).toBeTruthy();
      expect(apiRegisterMock).not.toHaveBeenCalled();
    });

    it('on success, logs in immediately (sets the cookie) and redirects to /compte — no separate login step', async () => {
      apiRegisterMock.mockResolvedValue({ user: { id: 1, name: 'Awa', email: 'awa@example.com' }, token: 'fresh-token' });

      await expect(
        registerAction(
          { error: null },
          formData({ name: 'Awa', email: 'awa@example.com', password: 'SuperSecret123!', password_confirmation: 'SuperSecret123!' })
        )
      ).rejects.toThrow('NEXT_REDIRECT:/compte');

      expect(setAuthCookieMock).toHaveBeenCalledWith('fresh-token');
    });

    it('surfaces field errors (e.g. email already taken) for inline display', async () => {
      apiRegisterMock.mockRejectedValue(
        new ApiError(422, { message: 'Validation failed.', errors: { email: ['Cet email est déjà utilisé.'] } })
      );

      const state = await registerAction(
        { error: null },
        formData({ name: 'Awa', email: 'taken@example.com', password: 'x', password_confirmation: 'x' })
      );

      expect(state.fieldErrors?.email).toEqual(['Cet email est déjà utilisé.']);
    });
  });

  describe('logoutAction', () => {
    it('revokes the token server-side, clears the cookie, and redirects to /', async () => {
      getAuthTokenMock.mockResolvedValue('active-token');
      apiLogoutMock.mockResolvedValue(undefined);

      await expect(logoutAction()).rejects.toThrow('NEXT_REDIRECT:/');

      expect(apiLogoutMock).toHaveBeenCalledWith('active-token');
      expect(clearAuthCookieMock).toHaveBeenCalled();
    });

    it('still clears the cookie and redirects even if the Laravel revocation call fails (best-effort, never blocks logout)', async () => {
      getAuthTokenMock.mockResolvedValue('active-token');
      apiLogoutMock.mockRejectedValue(new ApiError(0, { message: 'network down' }));

      await expect(logoutAction()).rejects.toThrow('NEXT_REDIRECT:/');

      expect(clearAuthCookieMock).toHaveBeenCalled();
    });

    it('skips the Laravel call and still clears/redirects when there is no token', async () => {
      getAuthTokenMock.mockResolvedValue(null);

      await expect(logoutAction()).rejects.toThrow('NEXT_REDIRECT:/');

      expect(apiLogoutMock).not.toHaveBeenCalled();
      expect(clearAuthCookieMock).toHaveBeenCalled();
    });
  });
});
