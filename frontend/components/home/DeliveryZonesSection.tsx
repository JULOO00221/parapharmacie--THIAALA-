import type { DeliveryZone } from '@/lib/api/types';
import { formatPrice } from '@/lib/utils/format';

/**
 * Zones de livraison (DESIGN.md §3). Les zones et leurs frais viennent de
 * l'API (/delivery-zones, les mêmes que le checkout) ; le retrait en
 * boutique est sans frais, comme dans le checkout. Délais par zone non
 * affichés : ils n'existent pas encore dans les données (DESIGN.md §5).
 */
export function DeliveryZonesSection({ zones }: { zones: DeliveryZone[] }) {
  const sorted = [...zones].sort((a, b) => Number.parseFloat(a.fee) - Number.parseFloat(b.fee));

  return (
    <section aria-labelledby="livraison-title" className="mx-auto max-w-[1440px] px-5 pt-[34px] sm:px-8 lg:px-16 lg:pt-[78px]">
      <div className="flex flex-col gap-3.5 lg:flex-row lg:gap-[72px]">
        <div className="flex flex-col gap-3.5 lg:w-[480px] lg:shrink-0 lg:gap-4">
          <p className="surtitre text-[10.5px] tracking-[0.2em] text-or lg:text-[12.5px] lg:tracking-[0.22em]">Livraison</p>
          <h2 id="livraison-title" className="font-titre text-[28px] leading-[1.15] text-vert lg:text-[38px]">
            Toute la région de Tambacounda
          </h2>
          <p className="text-[15px] leading-relaxed text-texte-doux lg:text-base lg:leading-[1.65]">
            Nous livrons à Tambacounda et dans la région — Koumpentoum, Goudiry, Bakel, Kidira, Kédougou. Vous
            pouvez aussi retirer votre commande à la parapharmacie.
          </p>
        </div>

        <ul className="grid flex-1 grid-cols-2 content-start gap-2.5 lg:gap-3.5">
          {sorted.map((zone) => (
            <li
              key={zone.id}
              className="flex min-h-[74px] flex-col justify-center gap-1 rounded-xl border border-bordure bg-blanc p-[13px] lg:min-h-[92px] lg:gap-1.5 lg:rounded-[14px] lg:p-[18px]"
            >
              <span className="text-sm font-semibold text-encre lg:text-base">{zone.name}</span>
              <span className="text-[11.5px] text-texte-discret lg:text-[13px]">Livraison {formatPrice(zone.fee)}</span>
            </li>
          ))}
          <li className="flex min-h-[74px] flex-col justify-center gap-1 rounded-xl border border-bordure bg-blanc p-[13px] lg:min-h-[92px] lg:gap-1.5 lg:rounded-[14px] lg:p-[18px]">
            <span className="text-sm font-semibold text-encre lg:text-base">Retrait en boutique</span>
            <span className="text-[11.5px] text-texte-discret lg:text-[13px]">Sans frais</span>
          </li>
        </ul>
      </div>
    </section>
  );
}
