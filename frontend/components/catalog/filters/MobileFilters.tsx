'use client';

import { useRef, type MouseEvent, type ReactNode } from 'react';
import { CloseIcon } from '@/components/ui/icons';

function FilterIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" className="h-[17px] w-[17px] text-vert">
      <path d="M4 7h16M7 12h10M10 17h4" />
    </svg>
  );
}

/**
 * Filtres mobiles derrière un bouton « Filtrer » avec compteur (DESIGN.md
 * §3). Panneau = <dialog> natif en mode modal : focus piégé, Échap et
 * retour du focus gérés par le navigateur. Le contenu (filtres) est rendu
 * côté serveur et passé en `children`. Le panneau se ferme dès qu'un lien
 * de filtre est suivi ou que le formulaire est envoyé.
 */
export function MobileFilters({ activeCount, children }: { activeCount: number; children: ReactNode }) {
  const dialogRef = useRef<HTMLDialogElement>(null);

  function close() {
    dialogRef.current?.close();
  }

  function handleClick(event: MouseEvent<HTMLDialogElement>) {
    // Clic sur le fond (hors du panneau) : le <dialog> lui-même est la cible.
    if (event.target === event.currentTarget) close();
    if ((event.target as HTMLElement).closest('a[href]')) close();
  }

  return (
    <>
      <button
        type="button"
        onClick={() => dialogRef.current?.showModal()}
        aria-haspopup="dialog"
        className="flex h-12 flex-1 items-center justify-center gap-[9px] rounded-full border border-bordure-forte bg-blanc text-[14.5px] font-semibold text-encre"
      >
        <FilterIcon />
        Filtrer
        {activeCount > 0 && (
          <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-vert px-1 text-[11.5px] text-white">
            <span className="sr-only">(</span>
            {activeCount}
            <span className="sr-only"> actif{activeCount > 1 ? 's' : ''})</span>
          </span>
        )}
      </button>

      <dialog
        ref={dialogRef}
        aria-labelledby="mobile-filters-title"
        onClick={handleClick}
        onSubmit={close}
        className="m-0 mt-auto max-h-[88dvh] w-full max-w-none overflow-hidden rounded-t-[22px] bg-ivoire p-0 text-encre backdrop:bg-encre/40 lg:hidden"
      >
        <div className="flex max-h-[88dvh] flex-col">
          <div className="flex items-center justify-between border-b border-bordure bg-blanc px-4 py-2">
            <h2 id="mobile-filters-title" className="font-titre text-xl text-vert">
              Filtrer
            </h2>
            <button
              type="button"
              onClick={close}
              aria-label="Fermer les filtres"
              className="flex h-11 w-11 items-center justify-center rounded-full text-encre hover:bg-ivoire"
            >
              <CloseIcon className="h-5 w-5" />
            </button>
          </div>
          <div className="flex-1 overflow-y-auto px-4 pb-6 pt-1">{children}</div>
        </div>
      </dialog>
    </>
  );
}
