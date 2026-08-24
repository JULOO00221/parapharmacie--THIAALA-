import Link from 'next/link';
import { Suspense } from 'react';
import { CartButton } from '@/components/cart/CartButton';
import { AccountNav } from './AccountNav';
import { MobileNav } from './MobileNav';
import { SearchBar } from './SearchBar';

function SearchBarFallback({ className }: { className?: string }) {
  return <div className={className} aria-hidden="true" />;
}

function AccountNavFallback() {
  return <div className="h-5 w-16" aria-hidden="true" />;
}

export function Header() {
  return (
    <header className="relative border-b border-border bg-surface-raised">
      <div className="mx-auto flex max-w-6xl items-center gap-6 px-4 py-4 sm:px-6">
        <Link href="/" className="shrink-0 text-lg font-semibold tracking-tight text-brand-700">
          Tambacounda <span className="text-accent-600">Cosmetix</span>
        </Link>

        <nav aria-label="Navigation principale" className="hidden md:flex md:items-center md:gap-6">
          <Link href="/" className="text-sm font-medium text-ink hover:text-brand-700">
            Accueil
          </Link>
          <Link href="/produits" className="text-sm font-medium text-ink hover:text-brand-700">
            Produits
          </Link>
        </nav>

        <Suspense fallback={<SearchBarFallback className="ml-auto hidden max-w-sm flex-1 md:block" />}>
          <SearchBar className="ml-auto hidden max-w-sm flex-1 md:block" />
        </Suspense>

        <div className="hidden md:block">
          <Suspense fallback={<AccountNavFallback />}>
            <AccountNav variant="desktop" />
          </Suspense>
        </div>

        <div className="ml-auto flex items-center gap-2 md:ml-0">
          <CartButton />
          <Suspense fallback={null}>
            <MobileNav
              accountSection={
                <Suspense fallback={null}>
                  <AccountNav variant="mobile" />
                </Suspense>
              }
            />
          </Suspense>
        </div>
      </div>
    </header>
  );
}
