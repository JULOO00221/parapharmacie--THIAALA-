/**
 * Coordonnées publiques de la parapharmacie. Le numéro WhatsApp n'est pas un
 * secret (il s'affiche sur le site), d'où NEXT_PUBLIC_ : Next l'inscrit dans
 * le code au moment du build.
 *
 * Il est obligatoire : sans lui, les boutons WhatsApp — au même niveau que
 * le panier (DESIGN.md §4.2) — ne mèneraient nulle part. Mieux vaut un
 * build qui échoue avec un message clair qu'un site aux boutons cassés.
 */
const RAW_NUMBER = process.env.NEXT_PUBLIC_WHATSAPP_NUMBER ?? '';
const WHATSAPP_NUMBER = RAW_NUMBER.replace(/\D/g, '');

if (WHATSAPP_NUMBER.length < 8) {
  throw new Error(
    'NEXT_PUBLIC_WHATSAPP_NUMBER manquant ou invalide : renseignez le numéro WhatsApp de la pharmacie, ' +
      'au format international sans « + » (ex. 221771234567), dans frontend/.env.local et sur l’hébergeur.',
  );
}

/** Lien wa.me, avec un message prérempli optionnel. */
export function whatsappHref(message?: string): string {
  const query = message ? `?text=${encodeURIComponent(message)}` : '';

  return `https://wa.me/${WHATSAPP_NUMBER}${query}`;
}

/** Numéro lisible, ex. « +221 77 123 45 67 ». */
export function whatsappDisplayNumber(): string {
  const local = WHATSAPP_NUMBER.startsWith('221') ? WHATSAPP_NUMBER.slice(3) : WHATSAPP_NUMBER;
  const grouped = local.replace(/^(\d{2})(\d{3})(\d{2})(\d{2})$/, '$1 $2 $3 $4');

  return WHATSAPP_NUMBER.startsWith('221') ? `+221 ${grouped}` : grouped;
}

/** Attributs d'un lien WhatsApp : toujours dans un nouvel onglet ou l'application. */
export const WHATSAPP_LINK_PROPS = { target: '_blank', rel: 'noopener noreferrer' } as const;
