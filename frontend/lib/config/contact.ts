/**
 * Coordonnées publiques de la parapharmacie. Le numéro WhatsApp n'est pas un
 * secret (il s'affiche sur le site), d'où NEXT_PUBLIC_. Tant qu'il n'est pas
 * renseigné, les boutons WhatsApp renvoient vers le bloc contact du pied de
 * page plutôt que vers un lien cassé.
 */
const WHATSAPP_NUMBER = (process.env.NEXT_PUBLIC_WHATSAPP_NUMBER ?? '').replace(/\D/g, '');

export const CONTACT_ANCHOR = '#contact';

export function hasWhatsApp(): boolean {
  return WHATSAPP_NUMBER.length > 0;
}

/** Lien wa.me, avec un message prérempli optionnel. */
export function whatsappHref(message?: string): string {
  if (!hasWhatsApp()) return CONTACT_ANCHOR;

  const query = message ? `?text=${encodeURIComponent(message)}` : '';

  return `https://wa.me/${WHATSAPP_NUMBER}${query}`;
}

/** Numéro lisible, ex. « +221 77 123 45 67 ». */
export function whatsappDisplayNumber(): string | null {
  if (!hasWhatsApp()) return null;

  const local = WHATSAPP_NUMBER.startsWith('221') ? WHATSAPP_NUMBER.slice(3) : WHATSAPP_NUMBER;
  const grouped = local.replace(/^(\d{2})(\d{3})(\d{2})(\d{2})$/, '$1 $2 $3 $4');

  return WHATSAPP_NUMBER.startsWith('221') ? `+221 ${grouped}` : grouped;
}
