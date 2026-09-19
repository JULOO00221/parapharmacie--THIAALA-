'use client';

import Image from 'next/image';
import { useRef, useState } from 'react';
import type { ProductImage } from '@/lib/api/types';
import { cn } from '@/lib/utils/cn';
import { ProductBadges } from './ProductBadges';
import { ProductImagePlaceholder } from './ProductImagePlaceholder';

/**
 * Galerie de la fiche produit (DESIGN.md §3).
 * - Mobile : carrousel pleine largeur à défilement magnétique (scroll-snap,
 *   donc au doigt sans JavaScript), avec des points de navigation.
 * - Desktop : visuel principal et miniatures en dessous (4 par ligne).
 * Sans photo, le visuel de remplacement habituel.
 */
export function ProductGallery({
  images,
  productName,
  featured = false,
  percentOff = null,
}: {
  images: ProductImage[];
  productName: string;
  featured?: boolean;
  percentOff?: number | null;
}) {
  const [activeIndex, setActiveIndex] = useState(0);
  const trackRef = useRef<HTMLDivElement>(null);

  if (images.length === 0) {
    return (
      <div className="relative -mx-4 sm:mx-0">
        <ProductImagePlaceholder className="aspect-square w-full sm:rounded-[22px] sm:border sm:border-bordure" />
        <ProductBadges featured={featured} percentOff={percentOff} />
      </div>
    );
  }

  const active = images[Math.min(activeIndex, images.length - 1)];

  function scrollToSlide(index: number) {
    const track = trackRef.current;
    if (!track) return;
    track.scrollTo({ left: index * track.clientWidth, behavior: 'smooth' });
  }

  return (
    <div>
      {/* Mobile : carrousel. */}
      <div className="relative -mx-4 sm:mx-0 lg:hidden">
        <div
          ref={trackRef}
          onScroll={(event) => {
            const track = event.currentTarget;
            setActiveIndex(Math.round(track.scrollLeft / track.clientWidth));
          }}
          className="flex snap-x snap-mandatory overflow-x-auto bg-ivoire-fonce [scrollbar-width:none] sm:rounded-[22px] [&::-webkit-scrollbar]:hidden"
          aria-label={`Photos de ${productName}`}
          role="region"
          tabIndex={0}
        >
          {images.map((image, index) => (
            <div key={image.url} className="relative aspect-square w-full shrink-0 snap-center">
              <Image
                src={image.url}
                alt={image.alt_text ?? `${productName} — photo ${index + 1}`}
                fill
                sizes="100vw"
                priority={index === 0}
                className="object-cover"
              />
            </div>
          ))}
        </div>
        <ProductBadges featured={featured} percentOff={percentOff} />

        {images.length > 1 && (
          <div className="flex justify-center bg-ivoire-fonce sm:bg-transparent">
            {images.map((image, index) => (
              <button
                key={image.url}
                type="button"
                onClick={() => scrollToSlide(index)}
                aria-label={`Voir la photo ${index + 1} sur ${images.length}`}
                aria-current={index === activeIndex ? 'true' : undefined}
                className="flex h-11 w-7 items-center justify-center"
              >
                <span
                  className={cn(
                    'h-[5px] rounded-full transition-all',
                    index === activeIndex ? 'w-[22px] bg-vert' : 'w-[5px] bg-bordure-forte',
                  )}
                />
              </button>
            ))}
          </div>
        )}
      </div>

      {/* Desktop : visuel principal + miniatures. */}
      <div className="hidden lg:block">
        <div className="relative aspect-square w-full overflow-hidden rounded-[22px] border border-bordure bg-ivoire-fonce">
          <Image
            src={active.url}
            alt={active.alt_text ?? productName}
            fill
            sizes="(min-width: 1440px) 620px, 45vw"
            className="object-cover"
            priority
          />
          <ProductBadges featured={featured} percentOff={percentOff} />
        </div>

        {images.length > 1 && (
          <div className="mt-4 grid grid-cols-4 gap-4">
            {images.map((image, index) => (
              <button
                key={image.url}
                type="button"
                onClick={() => setActiveIndex(index)}
                aria-label={`Voir la photo ${index + 1}`}
                aria-current={index === activeIndex ? 'true' : undefined}
                className={cn(
                  'relative aspect-square overflow-hidden rounded-[14px] bg-ivoire-fonce transition-colors',
                  index === activeIndex ? 'border-2 border-vert' : 'border border-bordure hover:border-bordure-forte',
                )}
              >
                <Image src={image.url} alt="" fill sizes="140px" className="object-cover" />
              </button>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
