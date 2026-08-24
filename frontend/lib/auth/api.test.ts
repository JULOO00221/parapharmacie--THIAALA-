import { afterEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from '../api/client';
import { apiLogin, apiLogout, apiMe, apiRegister } from './api';

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

const SAMPLE_AUTH_RESPONSE = { user: { id: 1, name: 'Awa Diop', email: 'awa@example.com' }, token: 'plain-text-token' };

describe('lib/auth/api', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('apiLogin', () => {
    it('posts email/password and resolves with {user, token}', async () => {
      const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse(SAMPLE_AUTH_RESPONSE));

      const result = await apiLogin({ email: 'awa@example.com', password: 'secret' });

      const [, init] = fetchSpy.mock.calls[0];
      expect(JSON.parse(init?.body as string)).toEqual({ email: 'awa@example.com', password: 'secret' });
      expect(result.token).toBe('plain-text-token');
      expect(result.user.email).toBe('awa@example.com');
    });

    it('throws an ApiError(422) on invalid credentials — never reveals which field is wrong beyond Laravel’s own generic message', async () => {
      vi.spyOn(global, 'fetch').mockResolvedValue(
        jsonResponse({ message: 'Validation failed.', errors: { email: ['Identifiants invalides.'] } }, 422)
      );

      const error = await apiLogin({ email: 'nobody@example.com', password: 'wrong' }).catch((e) => e);

      expect(error).toBeInstanceOf(ApiError);
      expect((error as ApiError).status).toBe(422);
    });

    it('throws an ApiError(429) when the login throttle is hit', async () => {
      vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ message: 'Too Many Attempts.' }, 429));

      const error = await apiLogin({ email: 'a@b.com', password: 'x' }).catch((e) => e);

      expect(error).toBeInstanceOf(ApiError);
      expect((error as ApiError).status).toBe(429);
    });
  });

  describe('apiRegister', () => {
    it('posts all four fields and resolves with {user, token} — registration logs in immediately', async () => {
      const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse(SAMPLE_AUTH_RESPONSE, 201));

      const result = await apiRegister({
        name: 'Awa Diop',
        email: 'awa@example.com',
        password: 'SuperSecret123!',
        password_confirmation: 'SuperSecret123!',
      });

      const [, init] = fetchSpy.mock.calls[0];
      expect(JSON.parse(init?.body as string)).toEqual({
        name: 'Awa Diop',
        email: 'awa@example.com',
        password: 'SuperSecret123!',
        password_confirmation: 'SuperSecret123!',
      });
      expect(result.token).toBe('plain-text-token');
    });

    it('throws an ApiError(422) with field errors on a taken email', async () => {
      vi.spyOn(global, 'fetch').mockResolvedValue(
        jsonResponse({ message: 'Validation failed.', errors: { email: ['Cet email est déjà utilisé.'] } }, 422)
      );

      const error = await apiRegister({
        name: 'Awa',
        email: 'taken@example.com',
        password: 'x',
        password_confirmation: 'x',
      }).catch((e) => e);

      expect(error).toBeInstanceOf(ApiError);
      expect((error as ApiError).payload.errors?.email).toEqual(['Cet email est déjà utilisé.']);
    });
  });

  describe('apiMe', () => {
    it('sends the token as a Bearer header and never in the URL', async () => {
      const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ data: SAMPLE_AUTH_RESPONSE.user }));

      await apiMe('secret-token-value');

      const [url, init] = fetchSpy.mock.calls[0];
      const headers = init?.headers as Record<string, string>;
      expect(headers.Authorization).toBe('Bearer secret-token-value');
      expect(String(url)).not.toContain('secret-token-value');
    });

    it('throws an ApiError(401) for an invalid or revoked token', async () => {
      vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ message: 'Unauthenticated.' }, 401));

      const error = await apiMe('revoked-token').catch((e) => e);

      expect(error).toBeInstanceOf(ApiError);
      expect((error as ApiError).status).toBe(401);
    });
  });

  describe('apiLogout', () => {
    it('sends the token as a Bearer header', async () => {
      const fetchSpy = vi.spyOn(global, 'fetch').mockResolvedValue(jsonResponse({ message: 'Déconnecté.' }));

      await apiLogout('secret-token-value');

      const [, init] = fetchSpy.mock.calls[0];
      const headers = init?.headers as Record<string, string>;
      expect(headers.Authorization).toBe('Bearer secret-token-value');
    });
  });
});
