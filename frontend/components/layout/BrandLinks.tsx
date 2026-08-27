import Link from 'next/link';
import type { Brand } from '@/lib/api/types';

export function BrandLinks({
  brands,
  onNavigate,
  className,
}: {
  brands: Brand[];
  onNavigate?: () => void;
  className?: string;
}) {
  return (
    <ul className={className}>
      {brands.map((brand) => (
        <li key={brand.id}>
          <Link
            href={`/marques/${brand.slug}`}
            onClick={onNavigate}
            className="block rounded-lg px-2 py-1.5 text-sm text-ink hover:bg-brand-50 hover:text-brand-700"
          >
            {brand.name}
          </Link>
        </li>
      ))}
    </ul>
  );
}
