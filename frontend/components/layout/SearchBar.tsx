'use client';

import { useRouter, useSearchParams } from 'next/navigation';
import { useId, useState, type FormEvent } from 'react';
import { SearchIcon } from '@/components/ui/icons';

export function SearchBar({
  className,
  placeholder = 'Rechercher un produit, une marque…',
}: {
  className?: string;
  placeholder?: string;
}) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [value, setValue] = useState(searchParams.get('q') ?? '');
  // Rendue deux fois (en-tête desktop + ligne mobile) : id unique par instance.
  // Texte à 16px sous xl : en dessous, iOS zoome la page au focus.
  const inputId = useId();

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const params = new URLSearchParams();
    if (value.trim()) {
      params.set('q', value.trim());
    }

    router.push(`/produits${params.toString() ? `?${params.toString()}` : ''}`);
  }

  return (
    <form onSubmit={handleSubmit} className={className} role="search">
      <label htmlFor={inputId} className="sr-only">
        Rechercher un produit
      </label>
      <div className="flex h-[46px] items-center gap-2.5 rounded-full border border-bordure bg-ivoire px-4 focus-within:border-vert">
        <SearchIcon className="h-[17px] w-[17px] shrink-0 text-texte-discret" />
        <input
          id={inputId}
          type="search"
          name="q"
          value={value}
          onChange={(event) => setValue(event.target.value)}
          placeholder={placeholder}
          className="w-full bg-transparent text-base text-encre xl:text-[13.5px] placeholder:text-texte-discret focus:outline-none"
        />
      </div>
    </form>
  );
}
