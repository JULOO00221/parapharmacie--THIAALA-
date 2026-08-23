import type { Metadata } from 'next';
import { FilterPanel } from '@/components/catalog/FilterPanel';
import { Pagination } from '@/components/catalog/Pagination';
import { ProductGrid } from '@/components/catalog/ProductGrid';
import { getBrands } from '@/lib/api/brands';
import { getCategories } from '@/lib/api/categories';
import { getProducts, type ProductFilters, type ProductSort } from '@/lib/api/products';
import { getTags } from '@/lib/api/tags';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'Tous les produits',
  description: 'Parcourez le catalogue complet Tambacounda Cosmetix : soins du visage, du corps, cheveux et hygiène.',
  alternates: { canonical: '/produits' },
};

const SORT_VALUES: ProductSort[] = ['name_asc', 'name_desc', 'price_asc', 'price_desc', 'newest', 'featured_first'];

function firstValue(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}

export default async function ProductsPage({ searchParams }: PageProps<'/produits'>) {
  const query = await searchParams;

  const q = firstValue(query.q);
  const category = firstValue(query.category);
  const brand = firstValue(query.brand);
  const tags = firstValue(query.tags);
  const priceMin = firstValue(query.price_min);
  const priceMax = firstValue(query.price_max);
  const inStock = firstValue(query.in_stock);
  const featured = firstValue(query.featured);
  const sortParam = firstValue(query.sort);
  const sort = SORT_VALUES.includes(sortParam as ProductSort) ? (sortParam as ProductSort) : undefined;
  const page = firstValue(query.page);
  const perPage = firstValue(query.per_page);

  const filters: ProductFilters = {
    q,
    category,
    brand,
    tags,
    price_min: priceMin ? Number(priceMin) : undefined,
    price_max: priceMax ? Number(priceMax) : undefined,
    in_stock: inStock === '1' ? true : undefined,
    featured: featured === '1' ? true : undefined,
    sort,
    page: page ? Number(page) : undefined,
    per_page: perPage ? Number(perPage) : undefined,
  };

  const [products, categories, brands, allTags] = await Promise.all([
    getProducts(filters),
    getCategories(),
    getBrands(),
    getTags(),
  ]);

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
      <header className="mb-6">
        <h1 className="text-2xl font-bold text-ink sm:text-3xl">Tous les produits</h1>
        <p className="mt-1 text-sm text-ink-muted">
          {products.meta.total} produit{products.meta.total > 1 ? 's' : ''}
          {q && <> pour « {q} »</>}
        </p>
      </header>

      <div className="flex flex-col gap-6 lg:flex-row">
        <FilterPanel
          categories={categories}
          brands={brands}
          tags={allTags}
          values={{
            q,
            category,
            brand,
            tags,
            price_min: priceMin,
            price_max: priceMax,
            in_stock: inStock,
            featured,
            sort,
          }}
        />

        <div className="flex-1">
          <ProductGrid products={products.data} />
          <Pagination meta={products.meta} basePath="/produits" searchParams={{ ...query, page: undefined }} />
        </div>
      </div>
    </div>
  );
}
