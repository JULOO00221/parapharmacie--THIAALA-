<?php

namespace App\Services\ImageSourcing\Dto;

use App\Models\Product;

/**
 * Ce qu'a donné la recherche pour un produit. Un échec de la source externe
 * et une absence réelle de correspondance sont deux choses différentes : le
 * premier se rejoue, le second appelle une photo prise en boutique. Le
 * rapport les distingue donc au lieu de les confondre en « non trouvé ».
 */
final readonly class SourcingOutcome
{
    public const CANDIDATE = 'candidat_enregistre';

    public const ALREADY_PENDING = 'deja_en_attente';

    public const NO_RESULT = 'aucun_resultat';

    public const LOW_CONFIDENCE = 'score_insuffisant';

    public const NO_PHOTO = 'resultat_sans_photo';

    public const SOURCE_UNAVAILABLE = 'source_indisponible';

    public const DOWNLOAD_FAILED = 'telechargement_echoue';

    public const IMAGE_TOO_HEAVY = 'image_trop_lourde';

    public const IMAGE_UNUSABLE = 'image_inutilisable';

    public const NOT_SEARCHABLE = 'nom_inexploitable';

    public const ALREADY_REJECTED = 'deja_rejete';

    private function __construct(
        public Product $product,
        public string $status,
        public ?int $confidence = null,
        public ?string $matchedName = null,
        public ?string $searchQuery = null,
        public ?string $detail = null,
    ) {}

    public static function make(
        Product $product,
        string $status,
        ?int $confidence = null,
        ?string $matchedName = null,
        ?string $searchQuery = null,
        ?string $detail = null,
    ): self {
        return new self($product, $status, $confidence, $matchedName, $searchQuery, $detail);
    }

    /** Une proposition a-t-elle été produite pour ce produit ? */
    public function isMatch(): bool
    {
        return in_array($this->status, [self::CANDIDATE, self::ALREADY_PENDING], true);
    }

    /** Le produit reste sans photo : il part dans le CSV à traiter à la main. */
    public function isUnmatched(): bool
    {
        return ! $this->isMatch();
    }

    /** L'échec vient-il de la source plutôt que du catalogue ? Ces produits méritent d'être rejoués. */
    public function isRetryable(): bool
    {
        return in_array($this->status, [self::SOURCE_UNAVAILABLE, self::DOWNLOAD_FAILED], true);
    }
}
