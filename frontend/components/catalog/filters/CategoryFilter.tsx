import type { Category } from '@/lib/api/types';
import { catalogHref, type CatalogParams, type CatalogScope } from '@/lib/catalog/params';
import { FilterOption } from './FilterOption';

/**
 * Catégories en liens (l'API ne filtre que sur une catégorie à la fois).
 * Les rayons de premier niveau sont listés ; celui qui contient la
 * catégorie courante est déplié pour montrer ses sous-catégories. Un rayon
 * inclut ses sous-catégories (filtre récursif de l'API), et son compte aussi.
 * Les catégories sans produit dans le périmètre courant sont masquées, sauf
 * celle qui est sélectionnée.
 */
export function CategoryFilter({
  categories,
  scope,
  params,
}: {
  categories: Category[];
  scope: CatalogScope;
  params: CatalogParams;
}) {
  const current = categories.find((category) => category.slug === params.category);
  const openSlug = current?.parent?.slug ?? current?.slug;
  const isShown = (category: Category) =>
    category.products_count === undefined ||
    category.products_count > 0 ||
    category.slug === params.category ||
    category.slug === openSlug;
  const topLevel = categories.filter((category) => !category.parent && isShown(category));

  if (topLevel.length === 0) return null;

  return (
    <ul className="flex flex-col">
      <li>
        <FilterOption href={catalogHref(scope, params, { category: null })} active={!params.category}>
          Toutes les catégories
        </FilterOption>
      </li>
      {topLevel.map((category) => {
        const children = (category.children ?? []).filter(isShown);

        return (
          <li key={category.id}>
            <FilterOption
              href={catalogHref(scope, params, { category: category.slug })}
              active={params.category === category.slug}
              count={category.products_count}
            >
              {category.name}
            </FilterOption>
            {category.slug === openSlug && children.length > 0 && (
              <ul className="ml-[27px] flex flex-col border-l border-bordure pl-3">
                {children.map((child) => (
                  <li key={child.id}>
                    <FilterOption
                      href={catalogHref(scope, params, { category: child.slug })}
                      active={params.category === child.slug}
                      count={child.products_count}
                      compact
                    >
                      {child.name}
                    </FilterOption>
                  </li>
                ))}
              </ul>
            )}
          </li>
        );
      })}
    </ul>
  );
}
