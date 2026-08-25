import { getAuthToken } from '@/lib/auth/server';

/**
 * Internal Next.js Route Handler — NOT part of the Laravel API v1
 * surface. Same purpose as /api/account/orders: lets an authenticated
 * checkout initiate a Wave payment with the Bearer token attached
 * server-side from the HttpOnly cookie, since CheckoutView (a Client
 * Component) never has access to it. Forwards the request body verbatim
 * to Laravel and relays its response as-is — PaymentService remains the
 * only place validating and pricing the payment attempt.
 */
export async function POST(request: Request, { params }: { params: Promise<{ orderNumber: string }> }): Promise<Response> {
  const { orderNumber } = await params;
  const token = await getAuthToken();
  const body = await request.text();

  const apiUrl = process.env.NEXT_PUBLIC_API_URL;
  if (!apiUrl) {
    return Response.json({ message: "NEXT_PUBLIC_API_URL n'est pas configuré." }, { status: 500 });
  }

  let laravelResponse: Response;
  try {
    laravelResponse = await fetch(`${apiUrl.replace(/\/$/, '')}/orders/${encodeURIComponent(orderNumber)}/payments`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body,
    });
  } catch {
    return Response.json({ message: "Impossible de contacter l'API." }, { status: 502 });
  }

  const payload = await laravelResponse.text();

  return new Response(payload, {
    status: laravelResponse.status,
    headers: { 'Content-Type': 'application/json' },
  });
}
