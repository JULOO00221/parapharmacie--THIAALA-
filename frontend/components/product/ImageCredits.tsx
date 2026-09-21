import type { ProductImage } from '@/lib/api/types';

/**
 * Crédits des photos de la fiche produit.
 *
 * Les photos issues d'Open Beauty Facts sont sous licence CC-BY-SA : les
 * afficher sans citer leur auteur ni leur licence n'est pas autorisé. Ce bloc
 * n'apparaît donc que s'il y a quelque chose à créditer, et disparaît dès que
 * toutes les photos d'un produit sont des photos maison.
 */
export function ImageCredits({ images }: { images: ProductImage[] }) {
  const credits = images
    .map((image) => image.attribution)
    .filter((attribution) => attribution !== undefined);

  if (credits.length === 0) return null;

  // Une même photo peut apparaître plusieurs fois dans la galerie : un seul
  // crédit par mention.
  const unique = [...new Map(credits.map((credit) => [credit.text, credit])).values()];

  return (
    <p className="text-[12px] leading-relaxed text-texte-discret">
      {unique.map((credit, index) => (
        <span key={credit.text}>
          {index > 0 && ' · '}
          {credit.source_url ? (
            <a href={credit.source_url} target="_blank" rel="noopener noreferrer nofollow" className="hover:underline">
              {credit.text}
            </a>
          ) : (
            credit.text
          )}
          {' — '}
          <a
            href={credit.license_url}
            target="_blank"
            rel="noopener noreferrer nofollow license"
            className="hover:underline"
          >
            licence {credit.license_code}
          </a>
        </span>
      ))}
    </p>
  );
}
