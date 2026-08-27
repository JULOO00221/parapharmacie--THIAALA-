'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import type { Brand, Category } from '@/lib/api/types';
import { BrandLinks } from './BrandLinks';
import { CategoryLinks } from './CategoryLinks';
import { SearchBar } from './SearchBar';

const LINKS = [
  { href: '/', label: 'Accueil' },
  { href: '/produits', label: 'Produits' },
];

export function MobileNav({
  accountSection,
  categories = [],
  brands = [],
}: {
  accountSection?: ReactNode;
  categories?: Category[];
  brands?: Brand[];
}) {
  const [open, setOpen] = useState(false);

  function close() {
    setOpen(false);
  }

  useEffect(() => {
    if (!open) return;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') close();
    }

    document.addEventListener('keydown', handleKeyDown);

    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [open]);

  return (
    <div className="xl:hidden">
      <button
        type="button"
        aria-expanded={open}
        aria-controls="mobile-nav-panel"
        aria-label={open ? 'Fermer le menu' : 'Ouvrir le menu'}
        onClick={() => setOpen((value) => !value)}
        className="flex h-10 w-10 items-center justify-center rounded-full border border-border text-ink"
      >
        {open ? (
          <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.7" className="h-5 w-5">
            <path d="M5 5l10 10M15 5 5 15" strokeLinecap="round" />
          </svg>
        ) : (
          <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.7" className="h-5 w-5">
            <path d="M3 6h14M3 10h14M3 14h14" strokeLinecap="round" />
          </svg>
        )}
      </button>

      {open && (
        <>
          <button
            type="button"
            aria-label="Fermer le menu"
            onClick={close}
            className="fixed inset-0 z-30 bg-ink/40"
          />

          <div
            id="mobile-nav-panel"
            className="absolute inset-x-0 top-full z-40 max-h-[calc(100vh-4rem)] overflow-y-auto border-b border-border bg-surface-raised p-4 shadow-lg"
          >
            <SearchBar className="mb-4" />
            <nav aria-label="Navigation principale">
              <ul className="flex flex-col gap-1">
                {LINKS.map((link) => (
                  <li key={link.href}>
                    <Link
                      href={link.href}
                      onClick={close}
                      className="block rounded-lg px-3 py-2.5 text-base font-medium text-ink hover:bg-brand-50"
                    >
                      {link.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </nav>

            {categories.length > 0 && (
              <details className="mt-2 border-t border-border pt-3">
                <summary className="cursor-pointer list-none px-3 py-2 text-sm font-semibold text-ink">Catégories</summary>
                <CategoryLinks categories={categories} onNavigate={close} className="mt-1 px-1" />
              </details>
            )}

            {brands.length > 0 && (
              <details className="mt-2 border-t border-border pt-3">
                <summary className="cursor-pointer list-none px-3 py-2 text-sm font-semibold text-ink">Marques</summary>
                <BrandLinks brands={brands} onNavigate={close} className="mt-1 px-1" />
              </details>
            )}

            {accountSection && <div className="mt-4 border-t border-border pt-4">{accountSection}</div>}
          </div>
        </>
      )}
    </div>
  );
}
