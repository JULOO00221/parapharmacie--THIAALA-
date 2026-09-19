/**
 * Shared corner-badge overlay for a product image — used by both
 * ProductCard (catalogue/homepage) and ProductGallery (product page), so
 * "featured" and "on promotion" always look the same wherever a product
 * image appears. ml-auto on the promo chip pushes it to the opposite
 * corner whether or not the featured chip is present, so both can show at
 * once without overlapping.
 */
export function ProductBadges({ featured, percentOff }: { featured: boolean; percentOff: number | null }) {
  if (!featured && percentOff === null) return null;

  return (
    <div className="absolute inset-x-3 top-3 flex items-start gap-2">
      {featured && (
        <span className="rounded-full bg-vert px-2.5 py-1 text-[11px] font-semibold tracking-[0.02em] text-white">Mis en avant</span>
      )}
      {percentOff !== null && (
        <span className="ml-auto rounded-full bg-promo-600 px-2.5 py-1 text-[11px] font-semibold text-white">
          −{percentOff}%
        </span>
      )}
    </div>
  );
}
