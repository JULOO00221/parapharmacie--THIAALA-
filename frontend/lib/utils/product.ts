import type { Product } from '@/lib/api/types';

function normalize(text: string): string {
  return text
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase();
}

/**
 * The short description to display, or null when it adds nothing. Much of
 * the imported catalogue repeats the product name as its short description
 * ("Savon noir traditionnel" / "Savon noir traditionnel"); showing it twice
 * is noise. Comparison ignores case, accents and whitespace.
 */
export function visibleShortDescription(product: Pick<Product, 'name' | 'short_description'>): string | null {
  const description = product.short_description?.trim();

  if (!description) return null;

  return normalize(description) === normalize(product.name) ? null : description;
}

