import { CTA } from '@/components/ui/cta';
import { WhatsAppIcon } from '@/components/ui/icons';
import { WHATSAPP_LINK_PROPS, whatsappHref } from '@/lib/config/contact';

/**
 * Bloc « Conseil du pharmacien » (DESIGN.md §2) : fond vert, surtitre or
 * clair, titre Fraunces blanc, bouton blanc vers WhatsApp.
 * - `band` (accueil) : bandeau pleine largeur.
 * - `card` (fiche produit) : carte à côté des onglets ; le message WhatsApp
 *   est prérempli avec le produit concerné.
 */
export function PharmacistAdvice({ productName, layout = 'band' }: { productName?: string; layout?: 'band' | 'card' }) {
  const message = productName
    ? `Bonjour, j'ai une question sur le produit « ${productName} » avant de commander.`
    : "Bonjour, j'ai une question sur un produit avant de commander.";

  if (layout === 'card') {
    return (
      <section
        aria-labelledby="conseil-title"
        className="flex flex-col gap-3 rounded-[18px] bg-vert px-[22px] py-6 lg:gap-[15px] lg:rounded-[22px] lg:px-9 lg:py-[34px]"
      >
        <p className="surtitre text-[10.5px] tracking-[0.2em] text-or-clair lg:text-[11.5px]">Conseil du pharmacien</p>
        <h2 id="conseil-title" className="font-titre text-[23px] leading-[1.2] text-white lg:text-[27px]">
          Ce produit convient-il à votre peau&nbsp;?
        </h2>
        <p className="text-sm leading-relaxed text-sur-vert lg:text-[15px] lg:leading-[1.65]">
          Grossesse, allaitement, peau réactive, traitement en cours&nbsp;: posez votre question avant de commander.
        </p>
        <a href={whatsappHref(message)} {...WHATSAPP_LINK_PROPS} className={`${CTA.onGreen} mt-1 w-full lg:mt-1.5`}>
          <WhatsAppIcon className="h-[18px] w-[18px]" />
          Écrire au pharmacien
        </a>
      </section>
    );
  }

  return (    <section aria-labelledby="conseil-title" className="mx-auto max-w-[1440px] px-5 pt-[34px] sm:px-8 lg:px-16 lg:pt-[78px]">
      <div className="flex flex-col gap-3.5 rounded-[20px] bg-vert px-6 py-7 lg:flex-row lg:items-center lg:justify-between lg:gap-12 lg:rounded-[26px] lg:px-16 lg:py-[72px]">
        <div className="flex max-w-[640px] flex-col gap-3.5 lg:gap-4">
          <p className="surtitre text-[10.5px] tracking-[0.2em] text-or-clair lg:text-[12.5px] lg:tracking-[0.22em]">
            Conseil du pharmacien
          </p>
          <h2 id="conseil-title" className="font-titre text-[26px] leading-[1.2] text-white lg:text-[38px] lg:leading-[1.18]">
            Derrière chaque commande, un vrai pharmacien.
          </h2>
          <p className="text-[14.5px] leading-relaxed text-sur-vert lg:text-base">
            Un doute sur un produit pour votre peau, votre bébé ou un traitement en cours&nbsp;? Écrivez-nous avant
            de commander.
          </p>
        </div>

        <a
          href={whatsappHref(message)}
          {...WHATSAPP_LINK_PROPS}
          className={`${CTA.onGreen} mt-1 shrink-0 lg:mt-0 lg:h-[58px] lg:px-[30px] lg:text-base`}
        >
          <WhatsAppIcon className="h-[19px] w-[19px]" />
          Poser une question
        </a>
      </div>
    </section>
  );
}
