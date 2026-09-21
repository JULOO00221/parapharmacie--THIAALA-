<?php

namespace App\Services\ImageSourcing;

/**
 * Validation d'un code-barres produit (GTIN-8, 12, 13 ou 14).
 *
 * Le champ `barcode` du catalogue contient en réalité deux choses très
 * différentes : de vrais codes-barres EAN, et des références internes du
 * logiciel de caisse (neuf chiffres, préfixées de zéros). Interroger une base
 * externe avec une référence interne ne renverrait jamais rien, consommerait
 * du quota d'API et, pire, pourrait tomber par hasard sur un produit sans
 * rapport. On ne cherche donc par code-barres que si le code est un GTIN
 * valide : bonne longueur ET clé de contrôle correcte.
 */
final class Gtin
{
    private const VALID_LENGTHS = [8, 12, 13, 14];

    public static function isValid(?string $barcode): bool
    {
        $digits = self::digitsOf($barcode);

        if ($digits === null) {
            return false;
        }

        return self::checkDigit(substr($digits, 0, -1)) === (int) $digits[strlen($digits) - 1];
    }

    /** Le code réduit à ses chiffres, ou null s'il ne peut pas être un GTIN. */
    public static function normalize(?string $barcode): ?string
    {
        return self::isValid($barcode) ? self::digitsOf($barcode) : null;
    }

    private static function digitsOf(?string $barcode): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $barcode);

        if ($digits === null || $digits === '') {
            return null;
        }

        return in_array(strlen($digits), self::VALID_LENGTHS, true) ? $digits : null;
    }

    /**
     * Clé de contrôle GS1 : somme pondérée des chiffres, de droite à gauche,
     * en alternant les poids 3 et 1, puis complément à la dizaine supérieure.
     */
    private static function checkDigit(string $payload): int
    {
        $sum = 0;
        $weight = 3;

        for ($i = strlen($payload) - 1; $i >= 0; $i--) {
            $sum += (int) $payload[$i] * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (10 - ($sum % 10)) % 10;
    }
}
