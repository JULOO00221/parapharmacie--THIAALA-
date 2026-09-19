/**
 * Text normalised for comparison or search: accents removed (NFD, then the
 * combining marks U+0300–U+036F dropped), whitespace collapsed, lower case.
 * "  Crème  SOLAIRE " and "creme solaire" compare equal.
 */
export function normalizeText(text: string): string {
  return text
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase();
}
