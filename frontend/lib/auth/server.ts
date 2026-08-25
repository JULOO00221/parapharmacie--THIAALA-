import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
import { cache } from 'react';
import type { User } from '../api/types';
import { apiMe } from './api';
import { ApiError } from '../api/client';

/**
 * The only place in this codebase allowed to read or write the auth
 * cookie. Nothing else — no Client Component, no NEXT_PUBLIC_* value —
 * ever sees the token. See the Phase 9 audit: architecture hybride.
 */
const AUTH_COOKIE_NAME = 'tc_token';

const COOKIE_OPTIONS = {
  httpOnly: true,
  secure: process.env.NODE_ENV === 'production',
  sameSite: 'lax' as const,
  path: '/',
  maxAge: 60 * 60 * 24 * 30, // 30 jours
};

/**
 * Only callable from a Server Action or Route Handler — Next.js throws
 * otherwise ("Cookies can only be modified..."). Callers in that context
 * are the only ones that ever call this.
 */
export async function setAuthCookie(token: string): Promise<void> {
  const store = await cookies();
  store.set(AUTH_COOKIE_NAME, token, COOKIE_OPTIONS);
}

export async function clearAuthCookie(): Promise<void> {
  const store = await cookies();
  store.delete(AUTH_COOKIE_NAME);
}

export async function getAuthToken(): Promise<string | null> {
  const store = await cookies();

  return store.get(AUTH_COOKIE_NAME)?.value ?? null;
}

/**
 * The only source of truth for "is this visitor logged in" — never the
 * mere presence of the cookie. Wrapped in React's cache() so multiple
 * calls within the same request (e.g. the desktop and mobile account nav
 * widgets) share a single /auth/me round-trip instead of duplicating it.
 */
export const getCurrentUser = cache(async (): Promise<User | null> => {
  const token = await getAuthToken();
  if (token === null) return null;

  try {
    return await apiMe(token);
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      try {
        // Only succeeds when called from a Server Action/Route Handler.
        // A Server Component calling getCurrentUser() (the common case,
        // e.g. every protected page) hits a read-only cookie store here —
        // that throw is expected and harmless: every protected page
        // re-validates via /me on its own next request regardless, so a
        // stale cookie left in place until the next mutable context
        // (login/logout) never lets a 401 be mistaken for "logged in".
        const store = await cookies();
        store.delete(AUTH_COOKIE_NAME);
      } catch {
        // Expected in a Server Component — see comment above.
      }
    }

    return null;
  }
});

/** For a protected Server Component page: redirects to /connexion instead of rendering when there is no valid session. */
export async function requireUser(): Promise<{ user: User; token: string }> {
  const user = await getCurrentUser();
  if (user === null) {
    redirect('/connexion');
  }

  // getCurrentUser() only returns non-null after a token was read and
  // validated against /me, so re-reading it here is safe and avoids
  // threading the token through an extra return value on the common path.
  const token = await getAuthToken();

  return { user, token: token as string };
}

/**
 * Same as requireUser() but never redirects — for a page reachable by
 * both guests and authenticated customers (e.g. the payment result
 * pages), where a null user is a normal, expected case handled by the
 * caller rather than a reason to bounce to /connexion.
 */
export async function getOptionalUser(): Promise<{ user: User | null; token: string | null }> {
  const user = await getCurrentUser();
  if (user === null) {
    return { user: null, token: null };
  }

  const token = await getAuthToken();

  return { user, token };
}
