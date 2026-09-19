import type { ReactNode } from 'react';
import type { DeliveryZone } from '@/lib/api/types';
import { formatPrice } from '@/lib/utils/format';

const iconProps = {
  'aria-hidden': true,
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 1.6,
  strokeLinecap: 'round',
  strokeLinejoin: 'round',
  className: 'h-[21px] w-[21px] shrink-0 text-vert lg:h-6 lg:w-6',
} as const;

/** Tarif de livraison le plus bas, issu des zones réelles de l'API. */
function cheapestZone(zones: DeliveryZone[]): DeliveryZone | null {
  return zones.reduce<DeliveryZone | null>(
    (best, zone) => (best === null || Number.parseFloat(zone.fee) < Number.parseFloat(best.fee) ? zone : best),
    null,
  );
}

/**
 * Bandeau de confiance en 4 points (DESIGN.md §3). Uniquement des faits
 * confirmés : la parapharmacie dépend d'une officine agréée à Tambacounda ;
 * paiements (OrderService::PAYMENT_METHODS) ; tarifs de livraison lus dans
 * l'API ; conseil par WhatsApp. Pas de « jours fixes par zone » ni de
 * « réponse gratuite » : ces promesses ne sont pas encore tenues.
 * Sur mobile, seuls les titres courts s'affichent.
 */
export function ReassuranceSection({ zones }: { zones: DeliveryZone[] }) {
  const cheapest = cheapestZone(zones);

  const items: Array<{ title: string; short: string; detail: string; icon: ReactNode }> = [
    {
      title: 'Pharmacie agréée',
      short: 'Pharmacie agréée',
      detail: 'Officine à Tambacounda',
      icon: (
        <svg {...iconProps}>
          <path d="M12 3l7 3v5.5c0 4.3-2.9 8.2-7 9.5-4.1-1.3-7-5.2-7-9.5V6z" />
        </svg>
      ),
    },
    {
      title: 'Livraison dans la région',
      short: 'Livraison région',
      detail: cheapest ? `Dès ${formatPrice(cheapest.fee)}` : 'Tambacounda et alentours',
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
      title: 'Payez à la livraison',
      short: 'Payez à la livraison',
      detail: 'Ou en ligne par Wave',
      icon: (
        <svg {...iconProps}>
          <rect x="2.5" y="5.5" width="19" height="13" rx="2.2" />
          <path d="M2.5 10h19" />
        </svg>
      ),
    },
    {
      title: 'Conseil du pharmacien',
      short: 'Conseil pharmacien',
      detail: "Par WhatsApp, avant l'achat",
      icon: (
        <svg {...iconProps}>
          <path d="M4 5h16v11H9l-5 4z" />
        </svg>
      ),
    },
  ];

  return (
    <section aria-label="Nos engagements" className="border-y border-bordure bg-blanc">
      <ul className="mx-auto grid max-w-[1440px] grid-cols-2 gap-[18px] px-5 py-5 sm:px-8 lg:grid-cols-4 lg:gap-6 lg:px-16 lg:py-[38px]">
        {items.map((item) => (
          <li key={item.title} className="flex items-center gap-2.5 lg:gap-3.5">
            {item.icon}
            <div className="flex flex-col gap-[3px]">
              <span className="text-[13px] font-semibold leading-tight text-encre lg:hidden">{item.short}</span>
              <span className="hidden text-[14.5px] font-semibold text-encre lg:inline">{item.title}</span>
              <span className="hidden text-[12.5px] text-texte-discret lg:inline">{item.detail}</span>
            </div>
          </li>
        ))}
      </ul>
    </section>
  );
}
