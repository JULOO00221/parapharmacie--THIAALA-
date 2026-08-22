<?php

namespace App\Services\Imports;

use Illuminate\Support\Str;

/**
 * Matches CSV header names to canonical product fields. Deliberately kept
 * to a small, curated alias list rather than a large fuzzy-matching engine.
 */
class ProductColumnMapper
{
    public const REQUIRED_FIELDS = ['name', 'sku', 'price'];

    /**
     * @var array<string, array<int, string>>
     */
    public const FIELD_ALIASES = [
        'name' => ['nom', 'name', 'produit'],
        'sku' => ['sku', 'reference', 'référence'],
        'barcode' => ['code-barres', 'code barres', 'codebarre', 'barcode', 'ean'],
        'price' => ['prix', 'price', 'prix de vente'],
        'cost_price' => ['prix d\'achat', 'prix achat', 'cost_price', 'cost price'],
        'compare_at_price' => ['prix barre', 'prix barré', 'compare_at_price', 'ancien prix'],
        'tax_rate' => ['tva', 'tax_rate', 'taux tva'],
        'category' => ['categorie', 'catégorie', 'category'],
        'brand' => ['marque', 'brand'],
        'short_description' => ['description courte', 'short_description'],
        'description' => ['description', 'description complete', 'description complète'],
        'is_active' => ['actif', 'is_active'],
        'is_featured' => ['mis en avant', 'is_featured', 'featured'],
        'requires_prescription' => ['ordonnance', 'requires_prescription', 'nécessite ordonnance', 'necessite ordonnance'],
        'weight' => ['poids', 'weight'],
        'tags' => ['tags', 'étiquettes', 'etiquettes'],
        'stock' => ['stock', 'stock initial', 'quantite', 'quantité'],
    ];

    /**
     * @param  array<int, string>  $headers
     * @return array<string, int|null> canonical field => header index (or null if unmatched)
     */
    public static function guessMapping(array $headers): array
    {
        $normalizedHeaders = array_map([self::class, 'normalize'], $headers);

        $mapping = [];

        foreach (self::FIELD_ALIASES as $field => $aliases) {
            $normalizedAliases = array_map([self::class, 'normalize'], $aliases);
            $index = array_search(true, array_map(
                fn (string $header) => in_array($header, $normalizedAliases, true),
                $normalizedHeaders
            ), true);

            $mapping[$field] = $index === false ? null : $index;
        }

        return $mapping;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<string, int|null>  $mapping
     * @return array<int, string> header indexes that matched no canonical field
     */
    public static function unmappedHeaderIndexes(array $headers, array $mapping): array
    {
        $mappedIndexes = array_filter(array_values($mapping), fn ($index) => $index !== null);

        return array_values(array_diff(array_keys($headers), $mappedIndexes));
    }

    /**
     * @param  array<string, int|null>  $mapping
     * @return array<int, string> canonical field names still missing
     */
    public static function missingRequiredFields(array $mapping): array
    {
        return array_values(array_filter(
            self::REQUIRED_FIELDS,
            fn (string $field) => ($mapping[$field] ?? null) === null
        ));
    }

    private static function normalize(string $value): string
    {
        return trim(mb_strtolower(Str::ascii($value)));
    }
}
