/**
 * Laravel serializes decimal columns as numeric strings (e.g. "1500.00").
 * FCFA is used without decimal subunits in everyday Senegalese commerce,
 * so amounts are rounded to whole francs for display.
 *
 * The single price formatter for the whole storefront. Intl's fr-FR output
 * groups thousands with a narrow no-break space (U+202F), which Fraunces
 * (the price typeface) lacks — "1 500" rendered as "1500". A regular
 * no-break space (U+00A0) is used instead, everywhere, so prices never
 * render differently from one component to another.
 */
const NARROW_NBSP = /\u202f/g;
const NBSP = '\u00a0';

const priceFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });

export function formatPrice(value: string | number): string {
  const amount = typeof value === 'string' ? Number.parseFloat(value) : value;

  if (Number.isNaN(amount)) {
    return '—';
  }

  return `${priceFormatter.format(amount).replace(NARROW_NBSP, NBSP)}${NBSP}FCFA`;
}
