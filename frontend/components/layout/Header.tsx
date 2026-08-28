import Image from 'next/image';
import Link from 'next/link';
import { Suspense } from 'react';
import type { ReactNode } from 'react';
import { CartButton } from '@/components/cart/CartButton';
import { getBrands } from '@/lib/api/brands';
import { getCategories } from '@/lib/api/categories';
import { AccountNav } from './AccountNav';
import { BrandLinks } from './BrandLinks';
import { CategoryLinks } from './CategoryLinks';
import { MobileNav } from './MobileNav';
import { SearchBar } from './SearchBar';

function SearchBarFallback({ className }: { className?: string }) {
  return <div className={className} aria-hidden="true" />;
}

function AccountNavFallback() {
  return <div className="h-5 w-16" aria-hidden="true" />;
}

/**
 * Zero-JS dropdown — same <details>/<summary> pattern already used by
 * FilterPanel's mobile accordion, so it's consistent with an existing
 * convention rather than a new interaction pattern. Trade-off accepted
 * deliberately: it doesn't auto-close on outside click (native <details>
 * limitation), same as FilterPanel already lives with.
 */
function HeaderDropdown({ label, children }: { label: string; children: ReactNode }) {
  return (
    // `name` makes the two Header dropdowns a native exclusive group — opening
    // one auto-closes the other — so their absolutely-positioned panels can
    // never end up open and overlapping at the same time.
    <details name="header-dropdown" className="group relative">
      <summary className="flex cursor-pointer list-none items-center gap-1 text-sm font-medium text-ink hover:text-brand-700">
        {label}
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.7" className="h-3.5 w-3.5 transition-transform group-open:rotate-180">
          <path d="m5 8 5 5 5-5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
      </summary>
      <div className="absolute left-0 top-full z-30 mt-2 w-64 rounded-xl border border-border bg-surface-raised p-4 shadow-lg">
        {children}
      </div>
    </details>
  );
}

export async function Header() {
  const [categories, brands] = await Promise.all([getCategories(), getBrands()]);

  return (
    <header className="relative border-b border-border bg-surface-raised">
      <div className="mx-auto flex max-w-6xl items-center gap-6 px-4 py-4 sm:px-6">
        {/*
          Official THIAALA lockup (icon + wordmark + slogan), trimmed from
          the source file's mostly-empty 1200x1200 canvas down to its real
          889x244 content — see public/logo-thiaala.png. Sized by height so
          it stays crisp at 2x; width follows the source aspect ratio.
        */}
        <Link href="/" className="relative block h-10 w-[145px] shrink-0 sm:h-12 sm:w-[174px]">
          <Image
            src="/logo-thiaala.png"
            alt="Parapharmacie THIAALA — Santé • Beauté • Bien-être"
            fill
            sizes="(min-width: 640px) 174px, 145px"
            className="object-contain object-left"
            priority
          />
        </Link>

        {/*
          xl (1280px), not md (768px): the two dropdowns added here (vs. the
          original Accueil/Produits-only nav) crowd out the search bar down
          to near-unusable width in the 768–1279px range — verified visually
          (search input shrank to ~4 visible characters at 1024px). MobileNav
          already fully covers Catégories/Marques below this breakpoint, so
          nothing is lost, only the crowding is avoided.
        */}
        <nav aria-label="Navigation principale" className="hidden xl:flex xl:items-center xl:gap-6">
          <Link href="/" className="text-sm font-medium text-ink hover:text-brand-700">
            Accueil
          </Link>
          <Link href="/produits" className="text-sm font-medium text-ink hover:text-brand-700">
            Produits
          </Link>
          <HeaderDropdown label="Catégories">
            <CategoryLinks categories={categories} />
          </HeaderDropdown>
          <HeaderDropdown label="Marques">
            <BrandLinks brands={brands} />
          </HeaderDropdown>
        </nav>

        <Suspense fallback={<SearchBarFallback className="ml-auto hidden max-w-sm flex-1 xl:block" />}>
          <SearchBar className="ml-auto hidden max-w-sm flex-1 xl:block" />
        </Suspense>

        {/*
          shrink-0: without it, this block was the one flex child that lost
          the width fight at the tight end of the xl range (~1280–1350px) —
          "Bonjour {name}" wrapped to two lines while every sibling stayed
          on one. SearchBar already declares itself the flexible element
          (flex-1 above), so it's the one that should absorb any squeeze,
          not this block's text.
        */}
        <div className="hidden shrink-0 xl:block">
          <Suspense fallback={<AccountNavFallback />}>
            <AccountNav variant="desktop" />
          </Suspense>
        </div>

        <div className="ml-auto flex items-center gap-2 xl:ml-0">
          <CartButton />
          <Suspense fallback={null}>
            <MobileNav
              categories={categories}
              brands={brands}
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
