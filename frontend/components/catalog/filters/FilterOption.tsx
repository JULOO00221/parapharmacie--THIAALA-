import Link from 'next/link';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils/cn';

/**
 * Une option de filtre sous forme de lien, avec un repère rond qui se
 * remplit quand l'option est active (choix unique, comme un bouton radio).
 * Cible de 44px de haut.
 */
export function FilterOption({
  href,
  active,
  compact = false,
  count,
  children,
}: {
  href: string;
  active: boolean;
  compact?: boolean;
  /** Nombre de produits derrière l'option, quand l'API le fournit. */
  count?: number;
  children: ReactNode;
}) {
  return (
    <Link
      href={href}
      aria-current={active ? 'true' : undefined}
      scroll={false}
      className={cn(
        'group flex min-h-11 items-center gap-[11px] rounded-lg text-left transition-colors hover:text-vert',
        compact ? 'text-sm' : 'text-[14.5px]',
        active ? 'font-semibold text-vert' : 'text-encre',
      )}
    >
      <span
        aria-hidden="true"
        className={cn(
          'flex h-4 w-4 shrink-0 items-center justify-center rounded-full border transition-colors',
          active ? 'border-vert' : 'border-bordure-forte group-hover:border-vert',
        )}
      >
        {active && <span className="h-2 w-2 rounded-full bg-vert" />}
      </span>
      <span className="min-w-0 flex-1">{children}</span>
      {count !== undefined && (
        <span className="shrink-0 text-[12.5px] font-normal text-texte-discret">
          {count}
          <span className="sr-only"> produit{count > 1 ? 's' : ''}</span>
        </span>
      )}
    </Link>
  );
}
