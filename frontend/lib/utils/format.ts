/**
 * Laravel serializes decimal columns as numeric strings (e.g. "1500.00").
 * FCFA is used without decimal subunits in everyday Senegalese commerce,
 * so amounts are rounded to whole francs for display.
 */
export function formatPrice(value: string | number): string {
  const amount = typeof value === 'string' ? Number.parseFloat(value) : value;

  if (Number.isNaN(amount)) {
    return '—';
  }

  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(amount)} FCFA`;
}
