import Link from 'next/link';
import type { PaginationMeta } from '@/lib/api/types';
import { pageWindow } from '@/lib/catalog/pagination';
import { cn } from '@/lib/utils/cn';

const CIRCLE =
  'flex h-11 min-w-11 items-center justify-center rounded-full border px-3 text-[14.5px] transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-vert';

function ChevronIcon({ direction }: { direction: 'left' | 'right' }) {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4">
      <path d={direction === 'left' ? 'M14 6l-6 6 6 6' : 'M10 6l6 6-6 6'} />
    </svg>
  );
}

/**
 * Numbered pagination — pure links to ?page=N preserving the other query
 * params, so it stays a Server Component. 44px targets throughout.
 */
export function Pagination({
  meta,
  basePath,
  searchParams,
}: {
  meta: PaginationMeta;
  basePath: string;
  searchParams: Record<string, string | string[] | undefined>;
}) {
  if (meta.last_page <= 1) {
    return null;
  }

  function hrefForPage(page: number): string {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(searchParams)) {
      const stringValue = Array.isArray(value) ? value[0] : value;
      if (stringValue && key !== 'page') {
        params.set(key, stringValue);
      }
    }
    if (page > 1) params.set('page', String(page));
    const query = params.toString();
    return query ? `${basePath}?${query}` : basePath;
  }

  const current = meta.current_page;
  const hasPrev = current > 1;
  const hasNext = current < meta.last_page;

  return (
    <nav aria-label="Pagination" className="mt-8 flex flex-wrap items-center justify-center gap-2 lg:mt-10 lg:gap-[9px]">
      {hasPrev ? (
        <Link href={hrefForPage(current - 1)} aria-label="Page précédente" className={cn(CIRCLE, 'border-bordure bg-blanc text-vert hover:border-vert')}>
          <ChevronIcon direction="left" />
        </Link>
      ) : (
        <span aria-hidden="true" className={cn(CIRCLE, 'border-bordure bg-blanc text-vert opacity-40')}>
          <ChevronIcon direction="left" />
        </span>
      )}

      {pageWindow(current, meta.last_page).map((page, index) =>
        page === 'gap' ? (
          <span key={`gap-${index}`} aria-hidden="true" className="w-6 text-center text-texte-discret">
            …
          </span>
        ) : page === current ? (
          <span key={page} aria-current="page" className={cn(CIRCLE, 'border-vert bg-vert font-semibold text-white')}>
            <span className="sr-only">Page </span>
            {page}
          </span>
        ) : (
          <Link key={page} href={hrefForPage(page)} className={cn(CIRCLE, 'border-bordure bg-blanc text-encre hover:border-vert')}>
            <span className="sr-only">Page </span>
            {page}
          </Link>
        ),
      )}

      {hasNext ? (
        <Link href={hrefForPage(current + 1)} aria-label="Page suivante" className={cn(CIRCLE, 'border-bordure bg-blanc text-vert hover:border-vert')}>
          <ChevronIcon direction="right" />
        </Link>
      ) : (
        <span aria-hidden="true" className={cn(CIRCLE, 'border-bordure bg-blanc text-vert opacity-40')}>
          <ChevronIcon direction="right" />
        </span>
      )}
    </nav>
  );
}
