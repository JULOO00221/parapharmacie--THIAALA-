/**
 * Grands boutons d'appel à l'action (DESIGN.md §1 « Formes ») : pilule,
 * 52px de haut sur mobile, 56px sur desktop. À appliquer sur un <a>/<Link>
 * ou un <button>. Les petits boutons de formulaire restent sur ui/Button.
 */
const BASE =
  'inline-flex h-[52px] items-center justify-center gap-2.5 rounded-full px-7 text-[15px] font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 sm:h-14 sm:text-[15.5px]';

export const CTA = {
  /** Vert plein, sur fond clair. */
  primary: `${BASE} bg-vert text-white hover:bg-brand-700 focus-visible:outline-vert`,
  /** Contour, sur fond clair. */
  secondary: `${BASE} border-[1.5px] border-bordure-forte text-vert hover:border-vert focus-visible:outline-vert`,
  /** Blanc plein, sur fond vert. */
  onGreen: `${BASE} bg-white text-vert hover:bg-ivoire focus-visible:outline-white`,
} as const;
