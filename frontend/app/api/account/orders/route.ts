import { NextResponse } from 'next/server';
import { getAuthToken } from '@/lib/auth/server';

/**
 * Internal Next.js Route Handler — NOT part of the Laravel API v1
 * surface. Sole purpose: let an authenticated checkout attach the Bearer
 * token from the HttpOnly cookie server-side, since CheckoutView (a
 * Client Component) never has access to it. Forwards the request body
 * and Idempotency-Key verbatim to Laravel and relays its response as-is
 * — StoreOrderRequest/OrderService remain the only place validating and
 * pricing the order. The guest path (POST straight to Laravel from
 * CheckoutView) is untouched by this route.
 */
export async function POST(request: Request): Promise<Response> {
  const token = await getAuthToken();
  const idempotencyKey = request.headers.get('Idempotency-Key');
  const body = await request.text();

  const apiUrl = process.env.NEXT_PUBLIC_API_URL;
  if (!apiUrl) {
    return NextResponse.json({ message: "NEXT_PUBLIC_API_URL n'est pas configuré." }, { status: 500 });
  }

  let laravelResponse: Response;
  try {
    laravelResponse = await fetch(`${apiUrl.replace(/\/$/, '')}/orders`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {}),
      },
      body,
    });
  } catch {
    return NextResponse.json({ message: "Impossible de contacter l'API." }, { status: 502 });
  }

  const payload = await laravelResponse.text();

  return new Response(payload, {
    status: laravelResponse.status,
    headers: { 'Content-Type': 'application/json' },
  });
}
