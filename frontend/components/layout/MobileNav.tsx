'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { CloseIcon, MenuIcon } from '@/components/ui/icons';
import type { Brand, Category } from '@/lib/api/types';
import { BrandLinks } from './BrandLinks';
import { CategoryLinks } from './CategoryLinks';

const LINKS = [
  { href: '/', label: 'Accueil' },
  { href: '/produits', label: 'Tous les produits' },
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
        className="flex h-11 w-11 items-center justify-center rounded-full text-encre hover:bg-ivoire"
      >
        {open ? <CloseIcon className="h-[21px] w-[21px]" /> : <MenuIcon className="h-[21px] w-[21px]" />}
      </button>

      {open && (
        <>
          <button
            type="button"
            aria-label="Fermer le menu"
            onClick={close}
            className="fixed inset-0 z-30 bg-encre/40"
          />

          <div
            id="mobile-nav-panel"
            className="absolute inset-x-0 top-full z-40 max-h-[calc(100dvh-4rem)] overflow-y-auto border-b border-bordure bg-blanc px-4 pb-6 pt-3"
          >
            <nav aria-label="Navigation principale">
              <ul className="flex flex-col gap-1">
                {LINKS.map((link) => (
                  <li key={link.href}>
                    <Link
                      href={link.href}
                      onClick={close}
                      className="flex min-h-11 items-center rounded-xl px-3 text-base font-medium text-encre hover:bg-ivoire"
                    >
                      {link.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </nav>

            {categories.length > 0 && (
              <details className="mt-2 border-t border-bordure pt-2">
                <summary className="flex min-h-11 cursor-pointer list-none items-center px-3 text-[12.5px] font-semibold uppercase tracking-[0.18em] text-or">Catégories</summary>
                <CategoryLinks categories={categories} onNavigate={close} className="mt-1 px-1" />
              </details>
            )}

            {brands.length > 0 && (
              <details className="mt-2 border-t border-bordure pt-2">
                <summary className="flex min-h-11 cursor-pointer list-none items-center px-3 text-[12.5px] font-semibold uppercase tracking-[0.18em] text-or">Marques</summary>
                <BrandLinks brands={brands} onNavigate={close} className="mt-1 px-1" />
              </details>
            )}

            {accountSection && <div className="mt-2 border-t border-bordure pt-2">{accountSection}</div>}
          </div>
        </>
      )}
    </div>
  );
}
