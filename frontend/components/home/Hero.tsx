import Image from 'next/image';
import Link from 'next/link';
import { CTA } from '@/components/ui/cta';
import { WhatsAppIcon } from '@/components/ui/icons';
import { WHATSAPP_LINK_PROPS, whatsappHref } from '@/lib/config/contact';

const ORDER_MESSAGE = 'Bonjour, je souhaite passer une commande auprès de la Parapharmacie THIAALA.';
const QUESTION_MESSAGE = "Bonjour, j'ai une question sur un produit avant de commander.";

/**
 * « Plus de 100 marques » — arrondi à la dizaine inférieure à partir du
 * nombre réel de marques du catalogue, pour que l'accroche reste vraie
 * quand le catalogue évolue. Rien en dessous de 20 : l'argument ne porte plus.
 */
function brandCountClaim(brandCount: number): string | null {
  if (brandCount < 20) return null;

  return `Plus de ${Math.floor(brandCount / 10) * 10} marques`;
}

/**
 * Visuel du héro. Aucune photo réelle n'existe encore (devanture ou
 * produits phares, DESIGN.md §5) : plutôt qu'une image inventée, le
 * pictogramme doré du logo sur le fond des visuels produits. Le jour où la
 * photo existe, elle remplace ce bloc sans toucher au reste.
 */
function HeroVisual() {
  return (
    <div className="relative">
      <div className="flex h-[190px] items-center justify-center rounded-[18px] border border-bordure bg-ivoire-fonce sm:h-[300px] lg:h-[440px] lg:rounded-[24px]">
        <Image
          src="/logo-icon.png"
          alt=""
          width={288}
          height={214}
          priority
          className="h-auto w-[112px] sm:w-[170px] lg:w-[220px]"
        />
      </div>

      {/* Carte flottante « Conseil du pharmacien » : à partir de xl (en dessous,
          la colonne du visuel est trop étroite et la carte le masque) ; sur
          mobile, le bloc conseil plus bas joue ce rôle. */}
      <a
        href={whatsappHref(QUESTION_MESSAGE)}
        {...WHATSAPP_LINK_PROPS}
        className="absolute -left-10 bottom-9 hidden w-[292px] flex-col gap-[7px] rounded-2xl border border-bordure bg-blanc px-[22px] py-5 shadow-[0_12px_30px_rgba(18,59,46,0.08)] transition-colors hover:border-bordure-forte xl:flex"
      >
        <span className="text-[11px] font-semibold uppercase tracking-[0.18em] text-or">Conseil du pharmacien</span>
        <span className="font-display text-lg leading-snug text-encre">Une question sur un produit&nbsp;?</span>
        <span className="text-[13.5px] leading-normal text-texte-discret">Écrivez-nous, on vous répond avant l&apos;achat.</span>
      </a>
    </div>
  );
}

export function Hero({ brandCount }: { brandCount: number }) {
  const claim = brandCountClaim(brandCount);

  return (
    <section className="mx-auto max-w-[1440px] px-5 pb-[34px] pt-[30px] sm:px-8 lg:px-16 lg:py-20">
      <div className="grid gap-[18px] lg:grid-cols-[minmax(0,570px)_minmax(0,1fr)] lg:grid-rows-[auto_auto_auto] lg:items-center lg:gap-x-[72px] lg:gap-y-8">
        <div className="flex flex-col gap-[18px] lg:gap-[26px] lg:self-end">
          <p className="surtitre text-[10.5px] tracking-[0.2em] text-or lg:text-[12.5px] lg:tracking-[0.22em]">
            Parapharmacie THIAALA · Tambacounda
          </p>
          <h1 className="font-titre text-[38px] leading-[1.08] text-vert sm:text-5xl lg:text-[62px] lg:leading-[1.06]">
            Les soins de votre pharmacie, livrés chez vous.
          </h1>
          <p className="max-w-[480px] text-[15px] leading-relaxed text-texte-doux lg:text-[17.5px] lg:leading-[1.65]">
            {claim ? `${claim} de soin — ` : ''}visage, corps, cheveux, bébé, hygiène. Commandez en ligne, recevez
            votre commande à Tambacounda et dans toute la région.
          </p>
        </div>

        <div className="lg:col-start-2 lg:row-span-3 lg:row-start-1">
          <HeroVisual />
        </div>

        <div className="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:gap-3.5 lg:self-start">
          <Link href="/produits" className={CTA.primary}>
            Découvrir le catalogue
          </Link>
          <a
            href={whatsappHref(ORDER_MESSAGE)}
            {...WHATSAPP_LINK_PROPS}
            className={CTA.secondary}
          >
            <WhatsAppIcon className="h-[18px] w-[18px]" />
            Commander sur WhatsApp
          </a>
        </div>

        <p className="flex items-start gap-2.5 text-[13px] leading-snug text-texte-discret lg:-mt-4 lg:items-center lg:text-[13.5px]">
          <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4 shrink-0">
            <path d="M12 3l7 3v5.5c0 4.3-2.9 8.2-7 9.5-4.1-1.3-7-5.2-7-9.5V6z" />
            <path d="M9.2 12.2l2 2 3.6-3.9" />
          </svg>
          Produits authentiques, issus des circuits pharmaceutiques officiels
        </p>
      </div>
    </section>
  );
}
