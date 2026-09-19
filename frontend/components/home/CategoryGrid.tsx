import Link from 'next/link';
import type { ReactNode } from 'react';
import type { Category } from '@/lib/api/types';
import { SectionHeading } from '@/components/ui/SectionHeading';

const RAYON_COUNT = 8;

const iconProps = {
  'aria-hidden': true,
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 1.6,
  strokeLinecap: 'round',
  strokeLinejoin: 'round',
  className: 'h-[19px] w-[19px] lg:h-[22px] lg:w-[22px]',
} as const;

const DEFAULT_ICON = (
  <svg {...iconProps}>
    <path d="M9 3h6v3.2l2.2 3.1V21H6.8V9.3L9 6.2z" />
    <path d="M6.8 13h10.4" />
  </svg>
);

/**
 * Ordre d'affichage des rayons et pictogramme de chacun, par slug de
 * catégorie. Présentation seulement : nom, sous-catégories et liens
 * viennent de l'API. Un slug absent de l'API est simplement ignoré, et la
 * grille se complète avec les autres rayons de premier niveau.
 */
const RAYONS: Array<{ slug: string; icon: ReactNode }> = [
  {
    slug: 'soins-du-visage',
    icon: (
      <svg {...iconProps}>
        <circle cx="12" cy="12" r="8.5" />
        <path d="M8.6 13.6c1.6 1.6 5.2 1.6 6.8 0" />
      </svg>
    ),
  },
  { slug: 'soins-du-corps', icon: DEFAULT_ICON },
  {
    slug: 'soins-capillaires',
    icon: (
      <svg {...iconProps}>
        <path d="M5 20c0-6 2.5-9 7-9s7 3 7 9" />
        <path d="M6.5 11C7 6.5 9.2 4 12 4s5 2.5 5.5 7" />
      </svg>
    ),
  },
  {
    slug: 'bebe-maternite',
    icon: (
      <svg {...iconProps}>
        <circle cx="12" cy="9" r="5.5" />
        <path d="M10 8.6h.01M14 8.6h.01" />
        <path d="M10.2 11.4c1 .9 2.6.9 3.6 0" />
        <path d="M6.5 20.5c1.2-2.6 3.2-3.9 5.5-3.9s4.3 1.3 5.5 3.9" />
      </svg>
    ),
  },
  {
    slug: 'solaires',
    icon: (
      <svg {...iconProps}>
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2.5v2.8M12 18.7v2.8M2.5 12h2.8M18.7 12h2.8M5.2 5.2l2 2M16.8 16.8l2 2M18.8 5.2l-2 2M7.2 16.8l-2 2" />
      </svg>
    ),
  },
  {
    slug: 'hygiene-savons',
    icon: (
      <svg {...iconProps}>
        <path d="M6.5 8.5h11l-1 11.5H7.5z" />
        <path d="M9.5 8.5V6a2.5 2.5 0 0 1 5 0v2.5" />
      </svg>
    ),
  },
  {
    slug: 'parfumerie',
    icon: (
      <svg {...iconProps}>
        <path d="M10 3h4v3h-4z" />
        <path d="M8.5 6h7l1.2 4.2c.6 2-.9 4-3 4h-3.4c-2.1 0-3.6-2-3-4z" />
        <path d="M12 14.2V21" />
      </svg>
    ),
  },
  {
    slug: 'hygiene-sante',
    icon: (
      <svg {...iconProps}>
        <path d="M12 21c-1-5.5 1.5-9.5 6.5-11C18 15.5 15.5 19 12 21z" />
        <path d="M12 21c-.6-4-2.7-6.6-6.5-7.6C5.8 17.6 8.4 20 12 21z" />
      </svg>
    ),
  },
];

function subtitle(category: Category): string | null {
  if (category.description) return category.description;

  const children = (category.children ?? []).map((child) => child.name);

  return children.length > 0 ? children.slice(0, 3).join(', ') : null;
}

/** Les 8 rayons : d'abord ceux de RAYONS, puis les autres catégories de premier niveau. */
function pickRayons(categories: Category[]): Array<{ category: Category; icon: ReactNode }> {
  // Rayons sans produit écartés (products_count couvre les sous-catégories).
  const topLevel = categories.filter((category) => !category.parent && category.products_count !== 0);
  const bySlug = new Map(topLevel.map((category) => [category.slug, category]));

  const preferred = RAYONS.flatMap(({ slug, icon }) => {
    const category = bySlug.get(slug);
    return category ? [{ category, icon }] : [];
  });
  const preferredSlugs = new Set(preferred.map(({ category }) => category.slug));
  const others = topLevel
    .filter((category) => !preferredSlugs.has(category.slug))
    .map((category) => ({ category, icon: DEFAULT_ICON }));

  return [...preferred, ...others].slice(0, RAYON_COUNT);
}

export function CategoryGrid({ categories }: { categories: Category[] }) {
  const rayons = pickRayons(categories);

  if (rayons.length === 0) return null;

  return (
    <section aria-labelledby="rayons-title" className="mx-auto max-w-[1440px] px-5 pt-[34px] sm:px-8 lg:px-16 lg:pt-[78px]">
      <SectionHeading
        id="rayons-title"
        eyebrow="Nos rayons"
        title="Trouvez ce qu'il vous faut"
        link={{ href: '/produits', label: 'Voir tout le catalogue' }}
      />

      <ul className="mt-[18px] grid grid-cols-2 gap-3 lg:mt-[34px] lg:grid-cols-4 lg:gap-[22px]">
        {rayons.map(({ category, icon }) => {
          const detail = subtitle(category);

          return (
            <li key={category.id}>
              <Link
                href={`/categories/${category.slug}`}
                className="flex h-full min-h-[108px] flex-col justify-between gap-4 rounded-[14px] border border-bordure bg-blanc p-3.5 transition-colors hover:border-vert lg:min-h-[178px] lg:rounded-[18px] lg:p-[22px]"
              >
                <span className="flex h-9 w-9 items-center justify-center rounded-[10px] bg-vert/[0.07] text-vert lg:h-[46px] lg:w-[46px] lg:rounded-xl">
                  {icon}
                </span>
                <span className="flex flex-col gap-[3px] lg:gap-[5px]">
                  <span className="text-[14.5px] font-semibold leading-tight text-encre lg:text-[17px]">{category.name}</span>
                  {detail && (
                    <span className="line-clamp-1 text-[11.5px] text-texte-discret lg:text-[13px]">{detail}</span>
                  )}
                </span>
              </Link>
            </li>
          );
        })}
      </ul>
    </section>
  );
}
