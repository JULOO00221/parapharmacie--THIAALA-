'use client';

import Link from 'next/link';
import { useState } from 'react';
import { SearchBar } from './SearchBar';

const LINKS = [
  { href: '/', label: 'Accueil' },
  { href: '/produits', label: 'Produits' },
];

export function MobileNav() {
  const [open, setOpen] = useState(false);

  return (
    <div className="md:hidden">
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
        <div id="mobile-nav-panel" className="absolute inset-x-0 top-full z-40 border-b border-border bg-surface-raised p-4 shadow-lg">
          <SearchBar className="mb-4" />
          <nav aria-label="Navigation principale">
            <ul className="flex flex-col gap-1">
              {LINKS.map((link) => (
                <li key={link.href}>
                  <Link
                    href={link.href}
                    onClick={() => setOpen(false)}
                    className="block rounded-lg px-3 py-2.5 text-base font-medium text-ink hover:bg-brand-50"
                  >
                    {link.label}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
        </div>
      )}
    </div>
  );
}
