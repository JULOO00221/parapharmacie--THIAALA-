import { CheckIcon } from '@/components/ui/icons';
import type { StockStatus } from '@/lib/api/types';
import { cn } from '@/lib/utils/cn';

const CONFIG: Record<StockStatus, { label: string; className: string }> = {
  // `vert-stock` est réservé à la mention « En stock » (DESIGN.md §1).
  in_stock: { label: 'En stock', className: 'text-vert-stock' },
  low_stock: { label: 'Plus que quelques unités', className: 'text-warning' },
  out_of_stock: { label: 'Rupture de stock', className: 'text-danger' },
};

/**
 * Disponibilité de la fiche produit : une ligne de texte avec pictogramme.
 * Indicative — Laravel revérifie le stock réel au paiement.
 */
export function StockBadge({ status }: { status: StockStatus }) {
  const { label, className } = CONFIG[status];

  return (
    <p className={cn('flex items-center gap-2 text-sm font-semibold lg:gap-[9px] lg:text-[14.5px]', className)}>
      {status === 'out_of_stock' ? (
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" className="h-4 w-4">
          <path d="M6 6l12 12M18 6 6 18" />
        </svg>
      ) : status === 'low_stock' ? (
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" className="h-4 w-4">
          <path d="M12 7v6M12 17h.01" />
        </svg>
      ) : (
        <CheckIcon className="h-4 w-4" strokeWidth={2.2} />
      )}
      {label}
    </p>
  );
}
