<?php

namespace App\Services\ImageSourcing\Dto;

/**
 * Un nom de produit du catalogue, nettoyé pour la recherche et la comparaison.
 */
final readonly class NormalizedName
{
    /**
     * @param  string  $text  Nom lisible, sans conditionnement ni contenance.
     * @param  list<string>  $tokens  Mots significatifs, sans accents ni mots-outils.
     * @param  string|null  $brand  Marque réelle, ou null si marque générique.
     * @param  string|null  $quantity  Contenance normalisée, ex. « 125 ml ».
     */
    public function __construct(
        public string $text,
        public array $tokens,
        public ?string $brand,
        public ?string $quantity,
    ) {}

    /**
     * Requêtes à essayer sur la source externe, de la plus précise à la plus
     * large. Une recherche trop longue ne renvoie rien sur Open Beauty Facts
     * (le moteur exige tous les mots) : il faut pouvoir se rabattre sur un
     * libellé plus court plutôt que d'abandonner le produit.
     *
     * @return list<string>
     */
    public function searchQueries(): array
    {
        $brand = $this->brand !== null ? mb_strtolower($this->brand) : null;
        $queries = [];

        foreach ([4, 2] as $keep) {
            $words = array_slice($this->tokens, 0, $keep);

            if ($words === []) {
                continue;
            }

            $queries[] = trim(($brand !== null ? $brand.' ' : '').implode(' ', $words));
        }

        if ($brand !== null) {
            $queries[] = $brand;
        }

        return array_values(array_unique(array_filter($queries)));
    }
}
