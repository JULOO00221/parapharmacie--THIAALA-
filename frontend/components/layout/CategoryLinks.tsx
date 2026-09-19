import Link from 'next/link';
import type { Category } from '@/lib/api/types';

/**
 * getCategories() returns a flat list where leaf categories carry `parent`
 * and group categories carry `children` — grouping by parent name here is
 * the only way to render "Parent > Child" without a second API shape.
 */
function groupByParent(categories: Category[]): Map<string, Category[]> {
  const leaves = categories.filter((category) => (category.children ?? []).length === 0);
  const groups = new Map<string, Category[]>();

  for (const leaf of leaves) {
    const groupName = leaf.parent?.name ?? leaf.name;
    const group = groups.get(groupName) ?? [];
    group.push(leaf);
    groups.set(groupName, group);
  }

  return groups;
}

export function CategoryLinks({
  categories,
  onNavigate,
  className,
}: {
  categories: Category[];
  onNavigate?: () => void;
  className?: string;
}) {
  const groups = groupByParent(categories);

  return (
    <div className={className}>
      {[...groups.entries()].map(([groupName, items]) => (
        <div key={groupName} className="mb-4 last:mb-0">
          <p className="mb-1 px-2 text-[11px] font-semibold uppercase tracking-[0.16em] text-texte-discret">{groupName}</p>
          <ul className="space-y-0.5">
            {items.map((item) => (
              <li key={item.id}>
                <Link
                  href={`/categories/${item.slug}`}
                  onClick={onNavigate}
                  className="flex min-h-10 items-center rounded-lg px-2 text-sm text-encre hover:bg-ivoire hover:text-vert"
                >
                  {item.name}
                </Link>
              </li>
            ))}
          </ul>
        </div>
      ))}
    </div>
  );
}
