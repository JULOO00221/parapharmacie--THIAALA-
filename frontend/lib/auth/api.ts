import { apiGet, apiPost } from '../api/client';
import type { AuthResponse, LoginPayload, RegisterPayload, User } from '../api/types';

/**
 * Pure Laravel auth calls — isomorphic fetch wrappers, same shape as
 * lib/api/orders.ts. No cookie handling here on purpose: that
 * orchestration lives in lib/auth/server.ts, which is the only place
 * allowed to see a token past the moment it's returned from these
 * functions.
 */

export async function apiRegister(payload: RegisterPayload): Promise<AuthResponse> {
  return apiPost<AuthResponse>('/auth/register', payload);
}

export async function apiLogin(payload: LoginPayload): Promise<AuthResponse> {
  return apiPost<AuthResponse>('/auth/login', payload);
}

/** Best-effort: callers decide how to handle a failure (see logoutAction). */
export async function apiLogout(token: string): Promise<void> {
  await apiPost<{ message: string }>('/auth/logout', {}, { headers: { Authorization: `Bearer ${token}` } });
}

export async function apiMe(token: string): Promise<User> {
  const response = await apiGet<{ data: User }>('/auth/me', { headers: { Authorization: `Bearer ${token}` } });

  return response.data;
}
