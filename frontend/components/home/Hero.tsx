import Link from 'next/link';
import { Button } from '@/components/ui/Button';

/**
 * No real hero photography exists yet — the seeded catalogue has zero
 * product images (see ProductImagePlaceholder's own doc comment). Rather
 * than fabricate a photo, this reuses the exact same droplet motif already
 * established as this brand's placeholder iconography, layered at a few
 * sizes/opacities into a soft, abstract composition. A real, already-shipped
 * asset — not an invented image.
 */
function HeroGraphic() {
  const droplet = 'M17 4h6v6.2c0 .8.3 1.6.9 2.2l6.4 6.6c1.8 1.9 2.7 4.4 2.7 7V30a6 6 0 0 1-6 6H13a6 6 0 0 1-6-6v-3.9c0-2.7.9-5.2 2.7-7.1l6.4-6.6c.6-.6.9-1.4.9-2.2V4Z';

  return (
    <div className="relative hidden aspect-square w-full max-w-md items-center justify-center lg:flex" aria-hidden="true">
      <div className="absolute h-full w-full rounded-full bg-accent-100/60" />
      <div className="absolute h-4/5 w-4/5 rounded-full bg-brand-50" />
      <svg viewBox="0 0 40 40" className="absolute left-8 top-12 h-16 w-16 text-brand-200" fill="none">
        <path d={droplet} stroke="currentColor" strokeWidth="2" strokeLinejoin="round" />
      </svg>
      <svg viewBox="0 0 40 40" className="relative h-1/2 w-1/2 text-brand-600" fill="none">
        <path d={droplet} stroke="currentColor" strokeWidth="1.5" strokeLinejoin="round" />
        <path d="M12 24h16" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
      </svg>
      <svg viewBox="0 0 40 40" className="absolute bottom-10 right-10 h-12 w-12 text-accent-600" fill="none">
        <path d={droplet} stroke="currentColor" strokeWidth="2" strokeLinejoin="round" />
      </svg>
    </div>
  );
}

export function Hero({ showNouveautesCta = false }: { showNouveautesCta?: boolean }) {
  return (
    <section className="border-b border-border bg-brand-600">
      <div className="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-2 lg:py-24">
        <div>
          <p className="text-sm font-medium uppercase tracking-wide text-brand-100">Parapharmacie & cosmétiques</p>
          <h1 className="mt-3 max-w-xl font-display text-4xl font-semibold text-white sm:text-5xl">
            Des soins sélectionnés avec soin, pour tout le Sénégal.
          </h1>
          <p className="mt-4 max-w-lg text-brand-50">
            Visage, corps, cheveux et hygiène — découvrez le catalogue Tambacounda Cosmetix.
          </p>

          <div className="mt-8 flex flex-wrap items-center gap-3">
            <Button href="/produits" variant="secondary" size="lg">
              Voir le catalogue
            </Button>
            {showNouveautesCta && (
              <Link
                href="#nouveautes"
                className="inline-flex items-center justify-center gap-2 rounded-full border border-white/40 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-white/10"
              >
                Voir les nouveautés
              </Link>
            )}
          </div>
        </div>

        <HeroGraphic />
      </div>
    </section>
  );
}
