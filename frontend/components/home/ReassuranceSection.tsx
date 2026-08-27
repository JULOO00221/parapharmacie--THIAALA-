const ITEMS = [
  {
    title: 'Paiement flexible',
    description: 'Réglez à la livraison, en boutique, ou en ligne avec Wave.',
    icon: (
      <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" className="h-5 w-5">
        <rect x="2.5" y="5" width="15" height="10" rx="1.5" />
        <path d="M2.5 8.5h15" strokeLinecap="round" />
      </svg>
    ),
  },
  {
    title: 'Retrait ou livraison',
    description: 'Choisissez de récupérer votre commande en boutique ou de vous faire livrer.',
    icon: (
      <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" className="h-5 w-5">
        <path d="M3 8l1.2-4h11.6L17 8" strokeLinecap="round" strokeLinejoin="round" />
        <path d="M3.5 8h13v7.5a1 1 0 0 1-1 1h-11a1 1 0 0 1-1-1V8Z" strokeLinejoin="round" />
        <path d="M8 9.5v3.5h4V9.5" strokeLinejoin="round" />
      </svg>
    ),
  },
  {
    title: 'Suivi de commande',
    description: "Suivez l'état de votre commande en ligne, à tout moment, avec votre numéro de commande.",
    icon: (
      <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" className="h-5 w-5">
        <circle cx="10" cy="10" r="7" />
        <path d="M10 6v4l2.5 2" strokeLinecap="round" strokeLinejoin="round" />
      </svg>
    ),
  },
];

/**
 * Same three factual capabilities as ReassuranceBar (sitewide top strip),
 * presented with more visual weight as a closing block before the footer.
 * Deliberately no rating, review count, "100% authentique", delivery
 * timing, or return-policy claim — none of that exists in the data model.
 */
export function ReassuranceSection() {
  return (
    <section className="border-y border-border bg-brand-50">
      <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <div className="grid grid-cols-1 gap-8 sm:grid-cols-3">
          {ITEMS.map((item) => (
            <div key={item.title} className="flex flex-col items-start gap-3">
              <span className="flex h-11 w-11 items-center justify-center rounded-full bg-surface-raised text-brand-700">
                {item.icon}
              </span>
              <h3 className="text-base font-semibold text-ink">{item.title}</h3>
              <p className="text-sm text-ink-muted">{item.description}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
