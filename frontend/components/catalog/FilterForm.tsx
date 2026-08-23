import type { Brand, Category, Tag } from '@/lib/api/types';

const SORT_OPTIONS: Array<{ value: string; label: string }> = [
  { value: 'newest', label: 'Plus récents' },
  { value: 'featured_first', label: 'Mis en avant' },
  { value: 'price_asc', label: 'Prix croissant' },
  { value: 'price_desc', label: 'Prix décroissant' },
  { value: 'name_asc', label: 'Nom (A → Z)' },
  { value: 'name_desc', label: 'Nom (Z → A)' },
];

interface FilterFormValues {
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
 * Plain GET <form> — no client JS needed. Submitting re-navigates to
 * /produits?... which the Server Component page reads via searchParams.
 * Works even with JavaScript disabled.
 */
export function FilterForm({
  categories,
  brands,
  tags,
  values,
}: {
  categories: Category[];
  brands: Brand[];
  tags: Tag[];
  values: FilterFormValues;
}) {
  return (
    <form method="get" action="/produits" className="space-y-5">
      {values.q && <input type="hidden" name="q" value={values.q} />}

      <div>
        <label htmlFor="filter-category" className="mb-1.5 block text-sm font-medium text-ink">
          Catégorie
        </label>
        <select
          id="filter-category"
          name="category"
          defaultValue={values.category ?? ''}
          className="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm"
        >
          <option value="">Toutes les catégories</option>
          {categories.map((category) => (
            <option key={category.id} value={category.slug}>
              {category.parent ? `${category.parent.name} — ${category.name}` : category.name}
            </option>
          ))}
        </select>
      </div>

      <div>
        <label htmlFor="filter-brand" className="mb-1.5 block text-sm font-medium text-ink">
          Marque
        </label>
        <select
          id="filter-brand"
          name="brand"
          defaultValue={values.brand ?? ''}
          className="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm"
        >
          <option value="">Toutes les marques</option>
          {brands.map((brand) => (
            <option key={brand.id} value={brand.slug}>
              {brand.name}
            </option>
          ))}
        </select>
      </div>

      <div>
        <label htmlFor="filter-tag" className="mb-1.5 block text-sm font-medium text-ink">
          Tag
        </label>
        <select
          id="filter-tag"
          name="tags"
          defaultValue={values.tags ?? ''}
          className="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm"
        >
          <option value="">Tous les tags</option>
          {tags.map((tag) => (
            <option key={tag.id} value={tag.slug}>
              {tag.name}
            </option>
          ))}
        </select>
      </div>

      <div>
        <span className="mb-1.5 block text-sm font-medium text-ink">Prix (FCFA)</span>
        <div className="flex items-center gap-2">
          <input
            type="number"
            name="price_min"
            min={0}
            placeholder="Min"
            defaultValue={values.price_min ?? ''}
            className="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm"
          />
          <span className="text-ink-muted">–</span>
          <input
            type="number"
            name="price_max"
            min={0}
            placeholder="Max"
            defaultValue={values.price_max ?? ''}
            className="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm"
          />
        </div>
      </div>

      <div className="space-y-2">
        <label className="flex items-center gap-2 text-sm text-ink">
          <input type="checkbox" name="in_stock" value="1" defaultChecked={values.in_stock === '1'} className="h-4 w-4 rounded border-border" />
          En stock uniquement
        </label>
        <label className="flex items-center gap-2 text-sm text-ink">
          <input type="checkbox" name="featured" value="1" defaultChecked={values.featured === '1'} className="h-4 w-4 rounded border-border" />
          Mis en avant uniquement
        </label>
      </div>

      <div>
        <label htmlFor="filter-sort" className="mb-1.5 block text-sm font-medium text-ink">
          Trier par
        </label>
        <select
          id="filter-sort"
          name="sort"
          defaultValue={values.sort ?? 'newest'}
          className="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm"
        >
          {SORT_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </div>

      <div className="flex gap-2">
        <button type="submit" className="flex-1 rounded-full bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700">
          Appliquer
        </button>
        <a
          href={values.q ? `/produits?q=${encodeURIComponent(values.q)}` : '/produits'}
          className="rounded-full border border-border px-4 py-2.5 text-sm font-medium text-ink hover:bg-brand-50"
        >
          Réinitialiser
        </a>
      </div>
    </form>
  );
}
