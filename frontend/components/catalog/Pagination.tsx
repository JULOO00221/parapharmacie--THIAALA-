import Link from 'next/link';
import type { PaginationMeta } from '@/lib/api/types';

/**
 * Pure links to ?page=N (preserving the other query params) — no client
 * interactivity needed, so this stays a Server Component.
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
    params.set('page', String(page));
    return `${basePath}?${params.toString()}`;
  }

  const hasPrev = meta.current_page > 1;
  const hasNext = meta.current_page < meta.last_page;

  return (
    <nav aria-label="Pagination" className="mt-8 flex items-center justify-center gap-4">
      {hasPrev ? (
        <Link href={hrefForPage(meta.current_page - 1)} className="rounded-full border border-border px-4 py-2 text-sm font-medium hover:bg-brand-50">
          ← Précédent
        </Link>
      ) : (
        <span className="rounded-full border border-border px-4 py-2 text-sm font-medium text-ink-muted opacity-50">← Précédent</span>
      )}

      <span className="text-sm text-ink-muted">
        Page {meta.current_page} sur {meta.last_page}
      </span>

      {hasNext ? (
        <Link href={hrefForPage(meta.current_page + 1)} className="rounded-full border border-border px-4 py-2 text-sm font-medium hover:bg-brand-50">
          Suivant →
        </Link>
      ) : (
        <span className="rounded-full border border-border px-4 py-2 text-sm font-medium text-ink-muted opacity-50">Suivant →</span>
      )}
    </nav>
  );
}
