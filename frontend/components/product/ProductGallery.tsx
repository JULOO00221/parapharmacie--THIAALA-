'use client';

import Image from 'next/image';
import { useState } from 'react';
import type { ProductImage } from '@/lib/api/types';
import { ProductImagePlaceholder } from './ProductImagePlaceholder';

export function ProductGallery({ images, productName }: { images: ProductImage[]; productName: string }) {
  const [activeIndex, setActiveIndex] = useState(0);

  if (images.length === 0) {
    return <ProductImagePlaceholder className="aspect-square w-full rounded-2xl" />;
  }

  const active = images[activeIndex];

  return (
    <div>
      <div className="relative aspect-square w-full overflow-hidden rounded-2xl bg-brand-50">
        <Image
          src={active.url}
          alt={active.alt_text ?? productName}
          fill
          sizes="(min-width: 1024px) 40vw, 100vw"
          className="object-cover"
          priority
        />
      </div>

      {images.length > 1 && (
        <div className="mt-3 flex gap-2">
          {images.map((image, index) => (
            <button
              key={image.url}
              type="button"
              onClick={() => setActiveIndex(index)}
              aria-label={`Voir l'image ${index + 1}`}
              aria-current={index === activeIndex}
              className={`relative h-16 w-16 overflow-hidden rounded-lg border-2 ${
                index === activeIndex ? 'border-brand-600' : 'border-transparent'
              }`}
            >
              <Image src={image.url} alt={image.alt_text ?? productName} fill sizes="64px" className="object-cover" />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
