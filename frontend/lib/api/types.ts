/**
 * Types mirroring the REAL JSON shapes returned by the Laravel API v1
 * (verified against actual responses, not assumed from the Eloquent
 * models). See app/Http/Resources/V1/* in the Laravel app for the
 * source of truth.
 */

export interface ProductImage {
  url: string;
  alt_text: string | null;
  sort_order: number;
}

export interface Brand {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  website: string | null;
  logo_url: string | null;
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
