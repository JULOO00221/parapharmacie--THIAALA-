import { describe, expect, it } from 'vitest';
import { formatPrice } from './format';

describe('formatPrice', () => {
  it('groups thousands and separates the currency with a regular no-break space', () => {
    expect(formatPrice('1500.00')).toBe('1 500 FCFA');
    expect(formatPrice(26240)).toBe('26 240 FCFA');
  });

  it('never emits the narrow no-break space missing from Fraunces', () => {
    expect(formatPrice(1234567)).not.toMatch(/ /);
  });

  it('rounds to whole francs', () => {
    expect(formatPrice('999.60')).toBe('1 000 FCFA');
  });

  it('renders a dash for non-numeric input', () => {
    expect(formatPrice('abc')).toBe('—');
  });
});
