import { afterEach, describe, expect, it, vi } from 'vitest';

async function loadContact(number: string | undefined) {
  vi.resetModules();
  vi.stubEnv('NEXT_PUBLIC_WHATSAPP_NUMBER', number as string);

  return import('./contact');
}

afterEach(() => {
  vi.unstubAllEnvs();
});

describe('contact config', () => {
  it('builds a wa.me link with a prefilled message', async () => {
    const { whatsappHref } = await loadContact('221 77 123 45 67');

    expect(whatsappHref()).toBe('https://wa.me/221771234567');
    expect(whatsappHref('Bonjour à vous')).toBe('https://wa.me/221771234567?text=Bonjour%20%C3%A0%20vous');
  });

  it('formats the number for display', async () => {
    const { whatsappDisplayNumber } = await loadContact('221771234567');

    expect(whatsappDisplayNumber()).toBe('+221 77 123 45 67');
  });

  it('fails loudly when the number is missing — no silent fallback', async () => {
    await expect(loadContact('')).rejects.toThrow(/NEXT_PUBLIC_WHATSAPP_NUMBER/);
  });
});
