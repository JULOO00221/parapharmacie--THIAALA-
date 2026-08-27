const ITEMS = [
  'Paiement à la livraison ou par Wave',
  'Retrait en boutique ou livraison',
  'Suivi de commande en ligne',
];

/**
 * Same three factual capabilities as ReassuranceBar/ReassuranceSection, in
 * a compact list suited to sitting right under the add-to-cart CTA. No
 * claim beyond what's already validated (no delivery time, no "guaranteed
 * returns", no authenticity claim, no reviews).
 */
export function ProductReassurance() {
  return (
    <ul className="mt-4 space-y-1.5">
      {ITEMS.map((item) => (
        <li key={item} className="flex items-center gap-2 text-xs text-ink-muted">
          <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.7" className="h-3.5 w-3.5 shrink-0 text-brand-600">
            <path d="M4 10.5l4 4 8-9" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
          <span>{item}</span>
        </li>
      ))}
    </ul>
  );
}
