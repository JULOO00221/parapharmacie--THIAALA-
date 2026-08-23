import type { Brand, Category, Tag } from '@/lib/api/types';
import { FilterForm } from './FilterForm';

interface FilterPanelValues {
  q?: string;
  category?: string;
  brand?: string;
  tags?: string;
  price_min?: string;
  price_max?: string;
  in_stock?: string;
  featured?: string;
  sort?: string;
}

/**
 * Renders the same zero-JS filter form twice: an always-visible sidebar on
 * desktop, and a native <details>/<summary> collapsible on mobile — no
 * client component needed for the show/hide behaviour.
 */
export function FilterPanel({
  categories,
  brands,
  tags,
  values,
}: {
  categories: Category[];
  brands: Brand[];
  tags: Tag[];
  values: FilterPanelValues;
}) {
  return (
    <>
      <details className="mb-6 rounded-2xl border border-border bg-surface-raised lg:hidden">
        <summary className="cursor-pointer list-none px-4 py-3 text-sm font-semibold text-ink">
          Filtres et tri
        </summary>
        <div className="border-t border-border p-4">
          <FilterForm categories={categories} brands={brands} tags={tags} values={values} />
        </div>
      </details>

      <aside className="hidden lg:block lg:w-64 lg:shrink-0">
        <div className="sticky top-6 rounded-2xl border border-border bg-surface-raised p-4">
          <h2 className="mb-4 text-sm font-semibold text-ink">Filtres et tri</h2>
          <FilterForm categories={categories} brands={brands} tags={tags} values={values} />
        </div>
      </aside>
    </>
  );
}
