import { describe, expect, it } from 'vitest';
import { visibleShortDescription } from './product';

describe('visibleShortDescription', () => {
  it('hides a description identical to the product name', () => {
    expect(visibleShortDescription({ name: 'Savon noir traditionnel', short_description: 'Savon noir traditionnel' })).toBeNull();
  });

  it('ignores case, accents and extra whitespace when comparing', () => {
    expect(visibleShortDescription({ name: 'Crème solaire SPF50', short_description: '  creme  SOLAIRE spf50 ' })).toBeNull();
  });

  it('keeps a description that says something more', () => {
    expect(visibleShortDescription({ name: 'BB Lait hydratant', short_description: 'Flacon 500 ml' })).toBe('Flacon 500 ml');
  });

  it('returns null for a missing or blank description', () => {
    expect(visibleShortDescription({ name: 'Savon', short_description: null })).toBeNull();
    expect(visibleShortDescription({ name: 'Savon', short_description: '   ' })).toBeNull();
  });
});
