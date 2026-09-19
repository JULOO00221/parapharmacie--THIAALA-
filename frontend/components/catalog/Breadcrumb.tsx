import Link from 'next/link';

export interface BreadcrumbItem {
  label: string;
  /** Absent pour la page courante. */
  href?: string;
}

export function Breadcrumb({ items }: { items: BreadcrumbItem[] }) {
  return (
    <nav aria-label="Fil d’Ariane">
      <ol className="flex flex-wrap items-center gap-x-2 text-xs text-texte-discret lg:gap-x-[9px] lg:text-[13px]">
        {items.map((item, index) => (
          <li key={`${item.label}-${index}`} className="flex items-center gap-x-2 lg:gap-x-[9px]">
            {index > 0 && <span aria-hidden="true">/</span>}
            {item.href ? (
              <Link href={item.href} className="py-1 hover:text-vert">
                {item.label}
              </Link>
            ) : (
              <span aria-current="page" className="text-encre">
                {item.label}
              </span>
            )}
          </li>
        ))}
      </ol>
    </nav>
  );
}
