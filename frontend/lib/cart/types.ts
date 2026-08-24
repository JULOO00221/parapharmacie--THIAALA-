/**
 * Cart items live entirely client-side (localStorage), never in Laravel.
 * Only `productId` and `quantity` are ever sent to the API at checkout —
 * every other field below is a display-only snapshot taken at add-to-cart
 * time. It must never be trusted as an authoritative price: Laravel always
 * reloads the real product row and recomputes everything server-side.
 */
export interface CartItem {
  productId: number;
  quantity: number;
  /** Display snapshot only — see the file-level note above. */
  name: string;
  slug: string;
  /** Numeric string, same shape as Product.price from the API. Display only. */
  price: string;
  imageUrl: string | null;
  sku: string;
}

export interface CartState {
  items: CartItem[];
}
