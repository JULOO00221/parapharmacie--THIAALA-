'use client';

import { useId, useState } from 'react';
import { SearchIcon } from '@/components/ui/icons';
import { normalizeText } from '@/lib/utils/text';
import { FilterOption } from './FilterOption';

export interface BrandOption {
  slug: string;
  name: string;
  href: string;
  active: boolean;
  count?: number;
}

/**
 * Marques en liens, avec un champ qui filtre la liste dans le navigateur
 * (DESIGN.md : « marque avec champ de recherche »). Les liens sont calculés
 * côté serveur ; ce composant ne fait que masquer ceux qui ne
 * correspondent pas. La marque active reste toujours visible.
 */
export function BrandFilter({ options, clearHref }: { options: BrandOption[]; clearHref: string }) {
  const [search, setSearch] = useState('');
  const inputId = useId();
  const needle = normalizeText(search);
  const visible = needle ? options.filter((option) => option.active || normalizeText(option.name).includes(needle)) : options;
  const noneActive = options.every((option) => !option.active);

  return (
    <div className="flex flex-col gap-3">
      <label htmlFor={inputId} className="sr-only">
        Chercher une marque
      </label>
      <div className="flex h-11 items-center gap-2.5 rounded-[10px] border border-bordure bg-ivoire px-3 focus-within:border-vert">
        <SearchIcon className="h-[15px] w-[15px] shrink-0 text-texte-discret" />
        <input
          id={inputId}
          type="search"
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder="Chercher une marque"
          autoComplete="off"
          className="w-full bg-transparent text-base text-encre placeholder:text-texte-discret focus:outline-none lg:text-[13px]"
        />
      </div>

      <ul className="-mr-2 flex max-h-[300px] flex-col overflow-y-auto pr-2" aria-label="Marques">
        {!needle && (
          <li>
            <FilterOption href={clearHref} active={noneActive}>
              Toutes les marques
            </FilterOption>
          </li>
        )}
        {visible.map((option) => (
          <li key={option.slug}>
            <FilterOption href={option.href} active={option.active} count={option.count}>
              {option.name}
            </FilterOption>
          </li>
        ))}
        {visible.length === 0 && <li className="py-2 text-sm text-texte-discret">Aucune marque ne correspond.</li>}
      </ul>
      <p className="sr-only" aria-live="polite">
        {needle ? `${visible.length} marque${visible.length > 1 ? 's' : ''} affichée${visible.length > 1 ? 's' : ''}` : ''}
      </p>
    </div>
  );
}
