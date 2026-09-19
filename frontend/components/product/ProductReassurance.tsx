import type { ReactNode } from 'react';
import { formatPrice } from '@/lib/utils/format';

const iconProps = {
  'aria-hidden': true,
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 1.6,
  strokeLinecap: 'round',
  strokeLinejoin: 'round',
  className: 'mt-px h-5 w-5 shrink-0 text-vert',
} as const;

/**
 * Encadré de réassurance de la fiche produit (DESIGN.md §3 : livraison /
 * retrait / authenticité). Uniquement des faits confirmés : frais de
 * livraison lus dans l'API (les mêmes qu'au paiement), retrait sans frais
 * (comme au paiement), authenticité validée. Pas de délai de livraison ni
 * de jours de tournée : ces promesses ne sont pas encore tenues.
 */
export function ProductReassurance({ cheapestDeliveryFee }: { cheapestDeliveryFee: string | null }) {
  const items: Array<{ title: string; detail: string; icon: ReactNode }> = [
    {
      title: 'Livraison à Tambacounda et dans la région',
      detail: cheapestDeliveryFee ? `Dès ${formatPrice(cheapestDeliveryFee)}, selon la zone` : 'Frais selon la zone, affichés au paiement',
      icon: (
        <svg {...iconProps}>
          <rect x="1.5" y="6.5" width="13" height="10" rx="1.6" />
          <path d="M14.5 10h3.6l2.9 3.1v3.4h-6.5z" />
          <circle cx="6" cy="18.3" r="1.8" />
          <circle cx="17" cy="18.3" r="1.8" />
        </svg>
      ),
    },
    {
      title: 'Retrait en boutique sans frais',
      detail: 'À la parapharmacie, à Tambacounda',
      icon: (
        <svg {...iconProps}>
          <path d="M4 7h16v12H4z" />
          <path d="M9 7V4.5h6V7" />
        </svg>
      ),
    },
    {
      title: 'Produit authentique',
      detail: 'Issu des circuits pharmaceutiques officiels',
      icon: (
        <svg {...iconProps}>
          <path d="M12 3l7 3v5.5c0 4.3-2.9 8.2-7 9.5-4.1-1.3-7-5.2-7-9.5V6z" />
          <path d="M9.2 12.2l2 2 3.6-3.9" />
        </svg>
      ),
    },
  ];

  return (
    <ul className="flex flex-col gap-4 rounded-2xl border border-bordure bg-blanc p-5 lg:rounded-[18px] lg:px-6 lg:py-[22px]">
      {items.map((item) => (
        <li key={item.title} className="flex items-start gap-3 lg:gap-[13px]">
          {item.icon}
          <div className="flex flex-col gap-[3px]">
            <span className="text-sm font-semibold text-encre lg:text-[14.5px]">{item.title}</span>
            <span className="text-[12.5px] text-texte-discret lg:text-[13px]">{item.detail}</span>
          </div>
        </li>
      ))}
    </ul>
  );
}
