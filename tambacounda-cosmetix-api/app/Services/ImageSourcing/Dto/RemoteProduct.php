<?php

namespace App\Services\ImageSourcing\Dto;

/**
 * Un produit tel que le renvoie Open Beauty Facts, réduit à ce dont on a
 * besoin : de quoi noter la correspondance, de quoi télécharger la photo, et
 * de quoi écrire l'attribution exigée par la licence.
 */
final readonly class RemoteProduct
{
    public function __construct(
        public string $code,
        public ?string $name,
        public ?string $brands,
        public ?string $quantity,
        public ?string $imageUrl,
        public ?string $photographer,
        public string $pageUrl,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload, string $baseUrl): ?self
    {
        $code = (string) ($payload['code'] ?? '');

        if ($code === '') {
            return null;
        }

        $imageUrl = self::bestImageUrl($payload);

        return new self(
            code: $code,
            name: self::string($payload['product_name'] ?? null),
            brands: self::string($payload['brands'] ?? null),
            quantity: self::string($payload['quantity'] ?? null),
            imageUrl: $imageUrl,
            photographer: self::photographerOf($payload, $imageUrl),
            pageUrl: rtrim($baseUrl, '/').'/product/'.$code,
        );
    }

    /**
     * `image_front_url` pointe sur la version 400 px, trop petite pour la
     * vignette de 800 px attendue : la redimensionner reviendrait à l'agrandir.
     * On en déduit l'URL pleine résolution, que l'on réduira nous-mêmes.
     */
    private static function bestImageUrl(array $payload): ?string
    {
        $front = self::string($payload['image_front_url'] ?? null);

        if ($front === null) {
            return null;
        }

        return preg_replace('/\.(\d+)\.jpg$/', '.full.jpg', $front) ?? $front;
    }

    /**
     * Le contributeur qui a pris la photo retenue. La licence CC-BY-SA impose
     * de le citer ; à défaut, on créditera la communauté Open Beauty Facts.
     *
     * Une fiche porte souvent plusieurs photos de face, une par langue
     * (`front_fr`, `front_en`), déposées par des contributeurs différents.
     * On identifie donc la bonne à partir de l'URL effectivement retenue,
     * sous peine de créditer quelqu'un dont on ne publie pas la photo.
     */
    private static function photographerOf(array $payload, ?string $imageUrl): ?string
    {
        $images = $payload['images'] ?? null;

        if ($imageUrl === null || ! is_array($images)) {
            return null;
        }

        // .../front_fr.12.full.jpg → « front_fr »
        if (preg_match('#/(front[^/.]*)\.#', $imageUrl, $matches) !== 1) {
            return null;
        }

        $selected = $images[$matches[1]] ?? null;

        if (! is_array($selected)) {
            return null;
        }

        // `imgid` renvoie à l'entrée de la photo d'origine, qui porte l'auteur.
        return self::string($images[(string) ($selected['imgid'] ?? '')]['uploader'] ?? null);
    }

    private static function string(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
