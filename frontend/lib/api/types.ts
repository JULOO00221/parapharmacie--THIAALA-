/**
 * Types mirroring the REAL JSON shapes returned by the Laravel API v1
 * (verified against actual responses, not assumed from the Eloquent
 * models). See app/Http/Resources/V1/* in the Laravel app for the
 * source of truth.
 */

/**
 * Mention imposée par la licence d'une photo venue d'une source externe
 * (Open Beauty Facts, sous CC-BY-SA). Absente des photos prises en boutique.
 * Quand elle est présente, elle doit être affichée avec l'image.
 */
export interface ImageAttribution {
  text: string;
  license_code: string;
  license_url: string;
  source_url: string | null;
}

export interface ProductImage {
  url: string;
  alt_text: string | null;
  sort_order: number;
  attribution?: ImageAttribution;
}

export interface Brand {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  website: string | null;
  logo_url: string | null;
  /** Active products. Only on GET /brands (scoped by ?category= when given), absent elsewhere. */
  products_count?: number;
}

/** Minimal category reference, used for Category.parent. */
export interface CategoryRef {
  id: number;
  name: string;
  slug: string;
}

export interface Category {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  image_url: string | null;
  /**
   * Only present on /categories and /categories/{slug} responses.
   * Omitted entirely (not `null`) when a category is nested inside a
   * Product, because ProductController doesn't eager-load
   * category.parent/category.children — Laravel's whenLoaded() drops
   * the key rather than nulling it in that case.
   */
  parent?: CategoryRef | null;
  children?: Category[];
  /**
   * Active products of the whole subtree — what /products?category= returns.
   * Only on GET /categories and /categories/{slug}; absent when nested in a product.
   */
  products_count?: number;
}

export interface Tag {
  id: number;
  name: string;
  slug: string;
}

export type StockStatus = 'in_stock' | 'low_stock' | 'out_of_stock';

export interface Product {
  id: number;
  name: string;
  slug: string;
  sku: string;
  short_description: string | null;
  description: string | null;
  /** Decimal columns are serialized by Laravel as numeric strings, e.g. "1500.00". */
  price: string;
  compare_at_price: string | null;
  tax_rate: string | null;
  weight: string | null;
  requires_prescription: boolean;
  featured: boolean;
  /** Nullable: brand_id is optional on the Product model. */
  brand: Brand | null;
  /** Never null: category_id is required on the Product model. */
  category: Category;
  tags: Tag[];
  primary_image: ProductImage | null;
  images: ProductImage[];
  available: boolean;
  stock_status: StockStatus;
}

export interface PaginationLink {
  url: string | null;
  label: string;
  page: number | null;
  active: boolean;
}

export interface PaginationMeta {
  current_page: number;
  from: number | null;
  last_page: number;
  links: PaginationLink[];
  path: string;
  per_page: number;
  to: number | null;
  total: number;
}

/** Laravel's default paginated JSON:API-ish resource collection envelope. */
export interface PaginatedResponse<T> {
  data: T[];
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
  meta: PaginationMeta;
}

export interface SingleResponse<T> {
  data: T;
}

/**
 * Shape of Laravel's JSON error bodies. `errors` is only present on 422
 * validation failures; other statuses (404, 500...) only carry `message`.
 * In local dev (APP_DEBUG=true) Laravel adds extra debug fields (exception,
 * file, line, trace) that are intentionally NOT modeled here — they never
 * appear in production and must not be relied on by the frontend.
 */
export interface ApiErrorPayload {
  message: string;
  errors?: Record<string, string[]>;
}

/** GET /stores — matches App\Http\Resources\V1\StoreResource. */
export interface Store {
  id: number;
  name: string;
  slug: string;
}

/** GET /delivery-zones — matches App\Http\Resources\V1\DeliveryZoneResource. */
export interface DeliveryZone {
  id: number;
  name: string;
  /** Numeric string, e.g. "1000.00" — same convention as Product.price. */
  fee: string;
}

/** GET /auth/me, and the `user` field of /auth/register + /auth/login — matches App\Http\Resources\V1\UserResource. */
export interface User {
  id: number;
  name: string;
  email: string;
}

/** POST /auth/register and POST /auth/login response body. The token is only ever read server-side (see lib/auth/*) — never forwarded to a Client Component. */
export interface AuthResponse {
  user: User;
  token: string;
}

export interface LoginPayload {
  email: string;
  password: string;
}

export interface RegisterPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export type OrderStatus = 'pending' | 'confirmed' | 'preparing' | 'ready' | 'delivered' | 'cancelled';
export type PaymentStatus = 'pending' | 'paid';
/** Whitelist enforced server-side by OrderService::PAYMENT_METHODS. 'orange_money' is intentionally absent — not wired up yet. */
export type PaymentMethod = 'cash_on_delivery' | 'cash_in_store' | 'wave';

/** Whitelist enforced server-side by PaymentService::SUPPORTED_PROVIDERS. */
export type PaymentProvider = 'wave';

/** A single payment ATTEMPT (payments table), distinct from Order.payment_status which is only a pending/paid summary. */
export type PaymentAttemptStatus = 'pending' | 'processing' | 'paid' | 'failed' | 'expired' | 'refunded';

/**
 * POST /orders/{order}/payments and GET .../payments/{transactionId}
 * response body — matches App\Http\Resources\V1\PaymentResource.
 * Deliberately excludes the internal id, order_id, external_reference and
 * raw metadata — never sent to the frontend. checkout_url is only
 * present while the attempt is still open (pending/processing).
 */
export interface Payment {
  transaction_id: string;
  provider: PaymentProvider;
  status: PaymentAttemptStatus;
  amount: string;
  currency: string;
  checkout_url: string | null;
  failure_reason: string | null;
  expires_at: string | null;
  paid_at: string | null;
  created_at: string;
}

export interface InitiatePaymentPayload {
  provider: PaymentProvider;
}

/**
 * A line item as returned by Laravel (App\Http\Resources\V1\OrderItemResource)
 * — a historical snapshot, not live product data. `product_id` is
 * nullable because order_items.product_id survives a product deletion.
 */
export interface OrderItem {
  product_id: number | null;
  product_name: string;
  sku: string;
  quantity: number;
  unit_price: string;
  subtotal: string;
}

/** Order.delivery.zone — only present when the order isn't a pickup. */
export interface OrderDeliveryZone {
  id: number;
  name: string;
  fee: string;
}

/** GET/POST /orders response body — matches App\Http\Resources\V1\OrderResource. */
export interface Order {
  order_number: string;
  status: OrderStatus;
  payment_status: PaymentStatus;
  payment_method: PaymentMethod;
  customer: {
    name: string;
    phone: string;
    email: string | null;
  };
  store: {
    id: number;
    name: string;
  };
  delivery: {
    is_pickup: boolean;
    zone: OrderDeliveryZone | null;
    address: string | null;
  };
  notes: string | null;
  items: OrderItem[];
  subtotal: string;
  delivery_fee: string;
  total: string;
  currency: string;
  created_at: string;
}

/**
 * POST /orders request body. Deliberately excludes every price/total/stock
 * field — Laravel recomputes all of that from products.price and real
 * stock, it is never trusted from the client. See lib/api/orders.ts.
 */
export interface CreateOrderPayload {
  items: Array<{ product_id: number; quantity: number }>;
  store_id: number;
  customer_name: string;
  customer_phone: string;
  customer_email?: string | null;
  is_pickup: boolean;
  delivery_zone_id?: number | null;
  delivery_address?: string | null;
  notes?: string | null;
  payment_method: PaymentMethod;
}
