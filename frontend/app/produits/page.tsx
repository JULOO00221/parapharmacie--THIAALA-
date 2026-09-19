import type { Metadata } from 'next';
import { CatalogView } from '@/components/catalog/CatalogView';
import { getBrands } from '@/lib/api/brands';
import { getCategories } from '@/lib/api/categories';
import { getProducts } from '@/lib/api/products';
import { CATALOG_PER_PAGE, parseCatalogParams, toProductFilters, type CatalogScope } from '@/lib/catalog/params';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'Tous les produits',
  description:
    'Tout le catalogue de la Parapharmacie THIAALA à Tambacounda : soins du visage, du corps, cheveux, bébé et hygiène, livrés dans toute la région.',
  alternates: { canonical: '/produits' },
};

const SCOPE: CatalogScope = { kind: 'all' };

export default async function ProductsPage({ searchParams }: PageProps<'/produits'>) {
  const params = parseCatalogParams(await searchParams, SCOPE);

  const [products, categories, brands] = await Promise.all([
    getProducts(toProductFilters(params, CATALOG_PER_PAGE)),
    // Comptes des filtres dans le périmètre courant : catégories comptées
    // pour la marque choisie, marques limitées à la catégorie choisie.
    getCategories({ brand: params.brand }),
    getBrands({ category: params.category }),
  ]);

  return (
    <CatalogView
      scope={SCOPE}
      params={params}
      title={params.q ? `Résultats pour « ${params.q} »` : 'Tous les produits'}
      description={
        params.q
          ? null
          : 'Soins du visage, du corps, des cheveux, bébé et hygiène : tout le catalogue, livré à Tambacounda et dans la région.'
      }
      breadcrumb={[{ label: 'Accueil', href: '/' }, { label: 'Tous les produits' }]}
      countContext={params.q ? `pour « ${params.q} »` : 'au catalogue'}
      products={products}
      categories={categories}
      brands={brands}
    />
  );
}
