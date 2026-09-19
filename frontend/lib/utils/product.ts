import type { Brand, Product } from '@/lib/api/types';

function normalize(text: string): string {
  return text
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
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

/**
 * The imported catalogue has placeholder brands for unbranded items
 * ("(accessoire, sans marque)", "(générique parfum)", "Autre"): real
 * catalogue entries, but not brands to showcase or count.
 */
export function isRealBrand(brand: Pick<Brand, 'name'>): boolean {
  return !brand.name.trim().startsWith('(') && brand.name.trim().toLowerCase() !== 'autre';
}
