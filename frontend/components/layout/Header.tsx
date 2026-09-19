import Image from 'next/image';
import Link from 'next/link';
import { Suspense } from 'react';
import type { ReactNode } from 'react';
import { CartButton } from '@/components/cart/CartButton';
import { ChevronDownIcon, UserIcon } from '@/components/ui/icons';
import { getBrands } from '@/lib/api/brands';
import { getCategories } from '@/lib/api/categories';
import { AccountNav } from './AccountNav';
import { BrandLinks } from './BrandLinks';
import { CategoryLinks } from './CategoryLinks';
import { MobileNav } from './MobileNav';
import { SearchBar } from './SearchBar';
import { WhatsAppButton } from './WhatsAppButton';

const NAV_LINK = 'flex h-11 items-center text-[15px] font-medium text-vert hover:text-or';

/**
 * Zero-JS dropdown — same <details>/<summary> pattern as FilterPanel's
 * mobile accordion. Trade-off accepted deliberately: it doesn't auto-close
 * on outside click (native <details> limitation).
 */
function HeaderDropdown({
  summary,
  summaryClassName,
  align = 'left',
  children,
}: {
  summary: ReactNode;
  summaryClassName: string;
  align?: 'left' | 'right';
  children: ReactNode;
}) {
  return (
    // `name` makes the Header dropdowns a native exclusive group — opening
    // one auto-closes the others — so their absolutely-positioned panels can
    // never end up open and overlapping at the same time.
    <details name="header-dropdown" className="group relative">
      <summary className={`cursor-pointer list-none [&::-webkit-details-marker]:hidden ${summaryClassName}`}>{summary}</summary>
      <div
        className={`absolute top-full z-30 mt-2 max-h-[70vh] w-64 overflow-y-auto rounded-[18px] border border-bordure bg-blanc p-3 ${
          align === 'right' ? 'right-0' : 'left-0'
        }`}
      >
        {children}
      </div>
    </details>
  );
}

function Logo({ className }: { className: string }) {
  return (
    // Logo officiel en SVG (fond transparent, 615 × 242). La hauteur fixe
    // la taille ; la largeur suit les proportions.
    <Link href="/" aria-label="Parapharmacie THIAALA — accueil" className="block shrink-0">
      <Image
        src="/logo-thiaala.svg"
        alt="Parapharmacie THIAALA — Santé, beauté, bien-être"
        width={615}
        height={242}
        unoptimized
        priority
        className={`w-auto ${className}`}
      />
    </Link>
  );
}

export async function Header() {
  const [categories, brands] = await Promise.all([getCategories(), getBrands()]);

  return (
    <header className="relative border-b border-bordure bg-blanc">
      {/*
        Desktop (xl+) : logo, navigation, recherche, compte, panier, WhatsApp.
        La navigation complète ne tient qu'à partir de 1280px ; en dessous,
        c'est la version mobile (menu burger), vérifié visuellement.
      */}
      <div className="mx-auto hidden h-[88px] max-w-[1440px] items-center gap-12 px-16 xl:flex">
        <Logo className="h-20" />

        <nav aria-label="Navigation principale" className="flex items-center gap-[30px]">
          <Link href="/" className={NAV_LINK}>
            Accueil
          </Link>
          <Link href="/produits" className={NAV_LINK}>
            Tous les produits
          </Link>
          <HeaderDropdown
            summaryClassName={NAV_LINK}
            summary={
              <span className="flex items-center gap-1">
                Catégories
                <ChevronDownIcon className="h-4 w-4 transition-transform group-open:rotate-180" />
              </span>
            }
          >
            <CategoryLinks categories={categories} />
          </HeaderDropdown>
          <HeaderDropdown
            summaryClassName={NAV_LINK}
            summary={
              <span className="flex items-center gap-1">
                Marques
                <ChevronDownIcon className="h-4 w-4 transition-transform group-open:rotate-180" />
              </span>
            }
          >
            <BrandLinks brands={brands} />
          </HeaderDropdown>
        </nav>

        <div className="flex flex-1 items-center justify-end gap-3">
          <Suspense fallback={<div className="h-[46px] w-[266px]" aria-hidden="true" />}>
            <SearchBar className="w-full max-w-[266px]" placeholder="Rechercher un produit…" />
          </Suspense>

          <HeaderDropdown
            align="right"
            summaryClassName="flex h-[46px] w-[46px] items-center justify-center rounded-full border border-bordure text-encre hover:bg-ivoire"
            summary={
              <>
                <UserIcon className="h-[19px] w-[19px]" />
                <span className="sr-only">Mon compte</span>
              </>
            }
          >
            <Suspense fallback={<div className="h-20" aria-hidden="true" />}>
              <AccountNav variant="desktop" />
            </Suspense>
          </HeaderDropdown>

          <CartButton />
          <WhatsAppButton variant="pill" />
        </div>
      </div>

      {/* Mobile : burger à gauche, logo centré, WhatsApp + panier à droite. */}
      <div className="relative grid h-[62px] grid-cols-[1fr_auto_1fr] items-center px-2 sm:px-4 xl:hidden">
        <div className="flex items-center">
          <Suspense fallback={<div className="h-11 w-11" aria-hidden="true" />}>
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

        <Logo className="h-[52px]" />

        <div className="flex items-center justify-end">
          <WhatsAppButton variant="icon" />
          <CartButton bordered={false} />
        </div>
      </div>

      {/* Mobile : recherche sur une ligne dédiée sous l'en-tête. */}
      <div className="border-t border-bordure px-4 py-2.5 xl:hidden">
        <Suspense fallback={<div className="h-[46px]" aria-hidden="true" />}>
          <SearchBar />
        </Suspense>
      </div>
    </header>
  );
}
