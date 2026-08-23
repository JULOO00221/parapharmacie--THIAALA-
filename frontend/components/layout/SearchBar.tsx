'use client';

import { useRouter, useSearchParams } from 'next/navigation';
import { useState, type FormEvent } from 'react';

export function SearchBar({ className }: { className?: string }) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [value, setValue] = useState(searchParams.get('q') ?? '');

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
      <label htmlFor="storefront-search" className="sr-only">
        Rechercher un produit
      </label>
      <div className="flex items-center gap-2 rounded-full border border-border bg-surface-raised px-4 py-2.5 focus-within:border-brand-400">
        <svg
          aria-hidden="true"
          viewBox="0 0 20 20"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.7"
          className="h-5 w-5 shrink-0 text-ink-muted"
        >
          <circle cx="9" cy="9" r="6.5" />
          <path d="m17.5 17.5-4-4" strokeLinecap="round" />
        </svg>
        <input
          id="storefront-search"
          type="search"
          name="q"
          value={value}
          onChange={(event) => setValue(event.target.value)}
          placeholder="Rechercher un produit, une marque…"
          className="w-full bg-transparent text-sm text-ink placeholder:text-ink-muted focus:outline-none"
        />
      </div>
    </form>
  );
}
