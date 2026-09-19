import { describe, expect, it } from 'vitest';
import { normalizeText } from './text';

describe('normalizeText', () => {
  it('drops accents, collapses whitespace and lowercases', () => {
    expect(normalizeText('  Crème  SOLAIRE ')).toBe('creme solaire');
    expect(normalizeText('Avène')).toBe(normalizeText('avene'));
  });
});
