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
            className="flex min-h-10 items-center rounded-lg px-2 text-sm text-encre hover:bg-ivoire hover:text-vert"
          >
            {brand.name}
          </Link>
        </li>
      ))}
    </ul>
  );
}
