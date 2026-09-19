/**
 * Bandeau d'annonce (DESIGN.md §2) : fond vert, une ligne, zones de
 * livraison + moyens de paiement. Statique : les zones reprennent la liste
 * de DESIGN.md, les moyens de paiement ceux d'OrderService::PAYMENT_METHODS.
 * Sur mobile, la liste des zones ne tient pas sur une ligne : on garde
 * Tambacounda (levier de référencement local) et le paiement à la livraison.
 */
export function AnnouncementBar() {
  return (
    <div className="bg-vert text-ivoire">
      <p className="mx-auto flex min-h-[38px] max-w-[1440px] items-center justify-center gap-x-3 px-4 sm:gap-x-7 py-2 text-center text-[11.5px] tracking-[0.03em] sm:min-h-11 sm:text-[13px] sm:tracking-[0.04em] lg:px-16">
        <span className="hidden lg:inline">
          Livraison à Tambacounda, Koumpentoum, Goudiry, Bakel, Kidira et Kédougou
        </span>
        <span className="hidden sm:inline lg:hidden">Livraison à Tambacounda et dans la région</span>
        <span className="sm:hidden">Livraison à Tambacounda</span>
        <span aria-hidden="true" className="opacity-45">
          |
        </span>
        <span>
          Paiement à la livraison<span className="hidden sm:inline"> ou par Wave</span>
        </span>
      </p>
    </div>
  );
}
