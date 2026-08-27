const ITEMS = [
  {
    label: 'Paiement à la livraison ou par Wave',
    icon: (
      <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" className="h-4 w-4 shrink-0">
        <rect x="2.5" y="5" width="15" height="10" rx="1.5" />
        <path d="M2.5 8.5h15" strokeLinecap="round" />
      </svg>
    ),
  },
  {
    label: 'Retrait en boutique ou livraison',
    icon: (
      <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" className="h-4 w-4 shrink-0">
        <path d="M3 8l1.2-4h11.6L17 8" strokeLinecap="round" strokeLinejoin="round" />
        <path d="M3.5 8h13v7.5a1 1 0 0 1-1 1h-11a1 1 0 0 1-1-1V8Z" strokeLinejoin="round" />
        <path d="M8 9.5v3.5h4V9.5" strokeLinejoin="round" />
      </svg>
    ),
  },
  {
    label: 'Suivi de commande en ligne',
    icon: (
      <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" className="h-4 w-4 shrink-0">
        <circle cx="10" cy="10" r="7" />
        <path d="M10 6v4l2.5 2" strokeLinecap="round" strokeLinejoin="round" />
      </svg>
    ),
  },
];

/**
 * Static, factual reassurance — every claim maps to a real, already-shipped
 * capability (OrderService::PAYMENT_METHODS, pickup/delivery at checkout,
 * /suivi-commande). No rating, review count, or "100% authentique"-style
 * claim: nothing in the data model backs those yet.
 */
export function ReassuranceBar() {
  return (
    <div className="border-b border-border bg-brand-50">
      <ul className="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-x-8 gap-y-2 px-4 py-2.5 text-xs font-medium text-brand-700 sm:px-6 sm:text-sm">
        {ITEMS.map((item) => (
          <li key={item.label} className="flex items-center gap-2">
            {item.icon}
            <span>{item.label}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}
