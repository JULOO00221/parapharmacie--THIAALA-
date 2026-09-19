import { describe, expect, it } from 'vitest';
import { formatPrice } from './format';

describe('formatPrice', () => {
  it('groups thousands and separates the currency with a regular no-break space', () => {
    expect(formatPrice('1500.00')).toBe('1\u00a0500\u00a0FCFA');
    expect(formatPrice(26240)).toBe('26\u00a0240\u00a0FCFA');
  });

  it('never emits the narrow no-break space missing from Fraunces', () => {
    expect(formatPrice(1234567)).not.toMatch(/\u202f/);
  });

  it('rounds to whole francs', () => {
    expect(formatPrice('999.60')).toBe('1\u00a0000\u00a0FCFA');
  });

  it('renders a dash for non-numeric input', () => {
    expect(formatPrice('abc')).toBe('—');
  });
});
