/**
 * A product is only "on promotion" when compare_at_price is a real, higher
 * reference price returned by the API — never inferred from `featured` or
 * any other field, and never assumed when compare_at_price is null.
 */
export function isDiscounted(price: string, compareAtPrice: string | null): boolean {
  if (!compareAtPrice) return false;

  const current = Number.parseFloat(price);
  const previous = Number.parseFloat(compareAtPrice);

  return Number.isFinite(current) && Number.isFinite(previous) && previous > current;
}

/** Rounded percentage off, derived only from real price/compare_at_price — null when not actually discounted. */
export function discountPercent(price: string, compareAtPrice: string | null): number | null {
  if (!isDiscounted(price, compareAtPrice)) return null;

  const current = Number.parseFloat(price);
  const previous = Number.parseFloat(compareAtPrice as string);

  return Math.round(((previous - current) / previous) * 100);
}
