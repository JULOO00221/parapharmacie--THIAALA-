import { CTA } from '@/components/ui/cta';
import { WhatsAppIcon } from '@/components/ui/icons';
import { hasWhatsApp, whatsappHref } from '@/lib/config/contact';

/**
 * Bloc « Conseil du pharmacien » (DESIGN.md §2) : fond vert, surtitre or
 * clair, titre Fraunces blanc, bouton blanc vers WhatsApp. Présent sur
 * l'accueil et, à terme, sur chaque fiche produit — `productName` préremplit
 * alors le message WhatsApp avec le produit concerné.
 */
export function PharmacistAdvice({ productName }: { productName?: string }) {
  const message = productName
    ? `Bonjour, j'ai une question sur le produit « ${productName} » avant de commander.`
    : "Bonjour, j'ai une question sur un produit avant de commander.";

  return (
    <section aria-labelledby="conseil-title" className="mx-auto max-w-[1440px] px-5 pt-[34px] sm:px-8 lg:px-16 lg:pt-[78px]">
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
          {...(hasWhatsApp() ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
          className={`${CTA.onGreen} mt-1 shrink-0 lg:mt-0 lg:h-[58px] lg:px-[30px] lg:text-base`}
        >
          <WhatsAppIcon className="h-[19px] w-[19px]" />
          Poser une question
        </a>
      </div>
    </section>
  );
}
