'use client';

import { useId, useRef, useState, type KeyboardEvent, type ReactNode } from 'react';
import { cn } from '@/lib/utils/cn';

export interface ProductTab {
  id: string;
  label: string;
  content: ReactNode;
}

/**
 * Onglets de la fiche produit (DESIGN.md §3 : Description · Composition ·
 * Conseils). La page ne passe que les onglets qui ont du contenu : avec un
 * seul, pas d'onglets, un simple titre de section.
 *
 * Motif ARIA « tabs » : flèches gauche/droite, Début/Fin, un seul onglet
 * dans l’ordre de tabulation (tabindex itinérant).
 */
export function ProductTabs({ tabs }: { tabs: ProductTab[] }) {
  const [activeId, setActiveId] = useState(tabs[0]?.id);
  const baseId = useId();
  const tabRefs = useRef<Array<HTMLButtonElement | null>>([]);

  if (tabs.length === 0) return null;

  if (tabs.length === 1) {
    const [tab] = tabs;
    return (
      <section aria-labelledby={`${baseId}-title`} className="flex flex-col gap-4 lg:gap-[26px]">
        <h2 id={`${baseId}-title`} className="border-b border-bordure pb-3 text-[14.5px] font-semibold text-encre lg:pb-[14px] lg:text-[15.5px]">
          {tab.label}
        </h2>
        {tab.content}
      </section>
    );
  }

  function focusTab(index: number) {
    const next = (index + tabs.length) % tabs.length;
    setActiveId(tabs[next].id);
    tabRefs.current[next]?.focus();
  }

  function handleKeyDown(event: KeyboardEvent<HTMLButtonElement>, index: number) {
    const moves: Record<string, number> = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: tabs.length - 1 };
    if (event.key in moves) {
      event.preventDefault();
      focusTab(moves[event.key]);
    }
  }

  return (
    <div className="flex flex-col gap-4 lg:gap-[26px]">
      <div role="tablist" aria-label="Informations produit" className="flex gap-[22px] overflow-x-auto border-b border-bordure lg:gap-[30px]">
        {tabs.map((tab, index) => {
          const selected = tab.id === activeId;
          return (
            <button
              key={tab.id}
              ref={(node) => {
                tabRefs.current[index] = node;
              }}
              type="button"
              role="tab"
              id={`${baseId}-tab-${tab.id}`}
              aria-selected={selected}
              aria-controls={`${baseId}-panel-${tab.id}`}
              tabIndex={selected ? 0 : -1}
              onClick={() => setActiveId(tab.id)}
              onKeyDown={(event) => handleKeyDown(event, index)}
              className={cn(
                '-mb-px min-h-11 shrink-0 border-b-2 pb-3 text-[14.5px] transition-colors lg:pb-[14px] lg:text-[15.5px]',
                selected ? 'border-vert font-semibold text-encre' : 'border-transparent text-texte-discret hover:text-encre',
              )}
            >
              {tab.label}
            </button>
          );
        })}
      </div>

      {tabs.map((tab) => (
        <div
          key={tab.id}
          role="tabpanel"
          id={`${baseId}-panel-${tab.id}`}
          aria-labelledby={`${baseId}-tab-${tab.id}`}
          hidden={tab.id !== activeId}
          tabIndex={0}
        >
          {tab.content}
        </div>
      ))}
    </div>
  );
}
