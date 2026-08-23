import { getBrands } from '@/lib/api/brands';
import { getCategories } from '@/lib/api/categories';
import { getProducts } from '@/lib/api/products';
import { getTags } from '@/lib/api/tags';
import { ClientSideCheck } from './ClientSideCheck';

export const dynamic = 'force-dynamic';

/**
 * TEMPORARY page — only exists to prove the Next.js -> Laravel API wiring
 * works with real data. Not part of the storefront. Safe to delete once
 * the real /produits pages exist.
 */
export default async function TestApiPage() {
  const [products, categories, brands, tags] = await Promise.all([
    getProducts({ per_page: 6, sort: 'featured_first' }),
    getCategories(),
    getBrands(),
    getTags(),
  ]);

  return (
    <main className="mx-auto max-w-3xl space-y-8 p-6">
      <h1 className="text-2xl font-bold">Vérification API Laravel v1</h1>

      <ClientSideCheck />

      <section className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <Stat label="Produits (total)" value={products.meta.total} />
        <Stat label="Catégories" value={categories.length} />
        <Stat label="Marques" value={brands.length} />
        <Stat label="Tags" value={tags.length} />
      </section>

      <section>
        <h2 className="mb-3 text-lg font-semibold">Échantillon de {products.data.length} produits</h2>
        <ul className="divide-y divide-gray-200">
          {products.data.map((product) => (
            <li key={product.id} className="flex items-center gap-4 py-3">
              {product.primary_image ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img
                  src={product.primary_image.url}
                  alt={product.primary_image.alt_text ?? product.name}
                  className="h-14 w-14 rounded object-cover"
                />
              ) : (
                <div className="flex h-14 w-14 items-center justify-center rounded bg-gray-200 text-xs text-gray-500">
                  Pas d&apos;image
                </div>
              )}

              <div className="flex-1">
                <p className="font-medium">{product.name}</p>
                <p className="text-sm text-gray-500">{product.category.name}</p>
              </div>

              <div className="text-right">
                <p className="font-semibold">{Number(product.price).toLocaleString('fr-FR')} FCFA</p>
                <StockBadge status={product.stock_status} available={product.available} />
              </div>
            </li>
          ))}
        </ul>
      </section>
    </main>
  );
}

function Stat({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded border border-gray-200 p-3 text-center">
      <p className="text-2xl font-bold">{value}</p>
      <p className="text-xs text-gray-500">{label}</p>
    </div>
  );
}

function StockBadge({ status, available }: { status: string; available: boolean }) {
  const color = available
    ? status === 'low_stock'
      ? 'text-orange-600'
      : 'text-green-700'
    : 'text-red-700';

  const label = { in_stock: 'En stock', low_stock: 'Stock faible', out_of_stock: 'Rupture' }[status] ?? status;

  return <p className={`text-xs font-medium ${color}`}>{label}</p>;
}
