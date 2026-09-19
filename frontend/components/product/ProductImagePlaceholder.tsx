import { cn } from '@/lib/utils/cn';

/**
 * Deliberate "no product photo yet" visual — the entire seeded catalogue
 * (24/24 products) has no image today, so this isn't an edge case, it's
 * the default. A plain ivoire-foncé tile (DESIGN.md: fond des visuels
 * produits) with a simple droplet icon reads as intentional, not as a
 * broken/missing image.
 */
export function ProductImagePlaceholder({ className }: { className?: string }) {
  return (
    <div
      className={cn('flex items-center justify-center bg-ivoire-fonce', className)}
      role="img"
      aria-label="Photo du produit non disponible"
    >
      <svg viewBox="0 0 40 40" className="h-1/4 w-1/4 text-bordure-forte" fill="none" aria-hidden="true">
        <path
          d="M17 4h6v6.2c0 .8.3 1.6.9 2.2l6.4 6.6c1.8 1.9 2.7 4.4 2.7 7V30a6 6 0 0 1-6 6H13a6 6 0 0 1-6-6v-3.9c0-2.7.9-5.2 2.7-7.1l6.4-6.6c.6-.6.9-1.4.9-2.2V4Z"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinejoin="round"
        />
        <path d="M12 24h16" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
      </svg>
    </div>
  );
}
