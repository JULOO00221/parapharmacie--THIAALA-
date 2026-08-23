import type { ApiErrorPayload } from './types';

const API_URL = process.env.NEXT_PUBLIC_API_URL;

/**
 * Thrown for both HTTP-level failures (4xx/5xx with a JSON body) and
 * network-level failures (timeout, abort, DNS, connection refused —
 * status 0). Callers can branch on `status` and read `payload.errors`
 * for 422 validation failures.
 */
export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly payload: ApiErrorPayload
  ) {
    super(payload.message);
    this.name = 'ApiError';
  }
}

export type QueryParams = Record<string, string | number | boolean | undefined | null>;

interface GetOptions {
  params?: QueryParams;
  /** Lets a caller (e.g. a React effect cleanup) cancel the request. */
  signal?: AbortSignal;
  /** Aborts the request client-side if Laravel never responds. Default 10s. */
  timeoutMs?: number;
  /** Passed straight through to fetch() for Next.js's server-side cache control. */
  cache?: RequestCache;
  next?: { revalidate?: number | false; tags?: string[] };
}

function requireApiUrl(): string {
  if (!API_URL) {
    throw new Error(
      'NEXT_PUBLIC_API_URL is not set. Define it in frontend/.env.local, e.g. ' +
        'NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api/v1'
    );
  }

  return API_URL;
}

function buildUrl(path: string, params?: QueryParams): string {
  const base = requireApiUrl();
  const normalizedBase = base.endsWith('/') ? base : `${base}/`;
  const url = new URL(path.replace(/^\//, ''), normalizedBase);

  if (params) {
    for (const [key, value] of Object.entries(params)) {
      if (value === undefined || value === null || value === '') {
        continue;
      }

      url.searchParams.set(key, String(value));
    }
  }

  return url.toString();
}

/**
 * GET a Laravel API v1 endpoint and parse its JSON response. Never throws
 * a raw fetch/DOMException — always a typed ApiError, so callers get a
 * consistent shape whether the failure was a 4xx/5xx from Laravel or a
 * network/timeout issue.
 */
export async function apiGet<T>(path: string, options: GetOptions = {}): Promise<T> {
  const { params, signal, timeoutMs = 10_000, cache, next } = options;
  const url = buildUrl(path, params);

  const timeoutController = new AbortController();
  const timeoutId = setTimeout(() => timeoutController.abort(), timeoutMs);
  const combinedSignal = signal ? AbortSignal.any([signal, timeoutController.signal]) : timeoutController.signal;

  try {
    const response = await fetch(url, {
      headers: { Accept: 'application/json' },
      signal: combinedSignal,
      cache,
      next,
    });

    const payload: unknown = await response.json().catch(() => null);

    if (!response.ok) {
      throw new ApiError(
        response.status,
        (payload as ApiErrorPayload | null) ?? { message: response.statusText || 'Erreur API inconnue.' }
      );
    }

    return payload as T;
  } catch (error) {
    if (error instanceof ApiError) {
      throw error;
    }

    if (error instanceof Error && error.name === 'AbortError') {
      throw new ApiError(0, { message: 'La requête vers l\'API a expiré ou a été annulée.' });
    }

    throw new ApiError(0, {
      message: error instanceof Error ? error.message : 'Erreur réseau inconnue lors de l\'appel à l\'API.',
    });
  } finally {
    clearTimeout(timeoutId);
  }
}
