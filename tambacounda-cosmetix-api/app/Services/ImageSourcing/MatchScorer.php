<?php

namespace App\Services\ImageSourcing;

use App\Services\ImageSourcing\Dto\NormalizedName;
use App\Services\ImageSourcing\Dto\RemoteProduct;

/**
 * Note de 0 à 100 la probabilité qu'une photo distante soit bien celle du
 * produit du catalogue.
 *
 * Cette note ne décide de rien : elle sert à trier ce qui sera présenté au
 * validateur, et à écarter le bruit évident. Un score de 100 reste une
 * proposition à valider — c'est la règle du projet, aucune photo n'est
 * publiée sans regard humain.
 *
 * Trois signaux, pondérés :
 *   - le nom (0,45) : c'est le signal principal, mais il est bruité, les
 *     libellés de caisse étant tronqués et parfois fautifs ;
 *   - la marque (0,35) : très discriminant, une marque différente écarte
 *     presque toujours le produit ;
 *   - la contenance (0,20) : départage deux formats d'un même produit, et
 *     seulement cela — elle est souvent absente d'un côté ou de l'autre,
 *     auquel cas elle reste neutre plutôt que pénalisante.
 */
class MatchScorer
{
    public function __construct(private readonly ProductNameNormalizer $normalizer) {}

    /**
     * @return array{score: int, breakdown: array<string, mixed>}
     */
    public function score(NormalizedName $local, RemoteProduct $remote): array
    {
        $remoteName = $this->normalizer->normalize((string) $remote->name);

        $nameScore = $this->nameScore($local->tokens, $remoteName->tokens);
        $brandScore = $this->brandScore($local->brand, $remote->brands);
        $quantityScore = $this->quantityScore($local->quantity, $remote->quantity);

        // Sans un seul mot en commun, rien ne rattache cette fiche à ce
        // produit précis : la bonne marque et la bonne contenance ne disent
        // que « c'est un produit de cette marque dans ce format », ce qui
        // décrit souvent une dizaine d'articles. La correspondance est donc
        // écartée d'emblée, sans quoi ces deux signaux suffisaient à eux
        // seuls à franchir le seuil.
        if ($nameScore === 0.0) {
            return [
                'score' => 0,
                'breakdown' => [
                    'aucun_mot_commun' => true,
                    'nom_distant' => $remote->name,
                    'mots_attendus' => $local->tokens,
                ],
            ];
        }

        $score = 0.45 * $nameScore + 0.35 * $brandScore + 0.20 * $quantityScore;

        // Deux marques connues et différentes : ce n'est pas le même produit,
        // quelle que soit la ressemblance des noms. Le catalogue contient des
        // parfums génériques nommés d'après ce qu'ils imitent
        // (« IAP EDP Nø32 OLYMPEA ») ; sans ce plafond, ils décrocheraient la
        // photo du parfum original.
        $cappedByBrand = $local->brand !== null && $remote->brands !== null && $brandScore === 0.0;

        if ($cappedByBrand) {
            $score = min($score, 0.45);
        }

        return [
            'score' => (int) round($score * 100),
            'breakdown' => [
                'marque_differente' => $cappedByBrand,
                'nom' => (int) round($nameScore * 100),
                'marque' => (int) round($brandScore * 100),
                'contenance' => (int) round($quantityScore * 100),
                'mots_communs' => array_values(array_intersect($local->tokens, $remoteName->tokens)),
                'mots_absents' => array_values(array_diff($local->tokens, $remoteName->tokens)),
            ],
        ];
    }

    /** Une correspondance par code-barres valide est certaine : le GTIN identifie le produit. */
    public function barcodeScore(): int
    {
        return 100;
    }

    /**
     * Part des mots du catalogue retrouvés dans le nom distant, relevée par la
     * ressemblance littérale des deux libellés. Le rappel seul est trop sévère
     * (« LRP EFFACLAR DUO+ SERUM » contre « Effaclar Duo+ » perd deux mots) ;
     * la ressemblance littérale seule est trop indulgente entre deux produits
     * d'une même gamme. On garde la plus favorable des deux, corrigée par une
     * pénalité quand le nom distant est beaucoup plus long — signe qu'il
     * désigne un autre article de la gamme.
     *
     * @param  list<string>  $local
     * @param  list<string>  $remote
     */
    private function nameScore(array $local, array $remote): float
    {
        if ($local === [] || $remote === []) {
            return 0.0;
        }

        $matched = 0;

        foreach ($local as $token) {
            foreach ($remote as $candidate) {
                if ($this->tokensMatch($token, $candidate)) {
                    $matched++;
                    break;
                }
            }
        }

        // Aucun mot en commun : il n'y a aucune preuve que ce soit le même
        // produit, et la ressemblance littérale n'en est pas une. C'est le
        // cas le plus dangereux, car les fiches distantes sont souvent
        // nommées d'après leur seule marque (« Signal », « Pierre Fabre ») :
        // elles ressemblent alors à tous les produits de la marque à la fois.
        if ($matched === 0) {
            return 0.0;
        }

        $recall = $matched / count($local);

        similar_text(implode(' ', $local), implode(' ', $remote), $percent);
        $literal = $percent / 100;

        $extraWords = max(0, count($remote) - count($local));
        $dilution = 1 - min(0.3, $extraWords * 0.06);

        return min(1.0, max($recall, $literal) * $dilution);
    }

    /**
     * Deux mots correspondent s'ils sont identiques, si l'un préfixe l'autre
     * (le libellé de caisse tronque : « concentre » / « concentrement »), ou
     * s'ils sont à une faute de frappe l'un de l'autre — le catalogue en
     * contient (« DEMAQILLANT », « ANTI PPOUX »).
     */
    private function tokensMatch(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $shortest = min(strlen($a), strlen($b));

        if ($shortest >= 4 && (str_starts_with($a, $b) || str_starts_with($b, $a))) {
            return true;
        }

        return $shortest >= 5 && levenshtein($a, $b) <= 1;
    }

    private function brandScore(?string $local, ?string $remote): float
    {
        // Sans marque de notre côté, la marque distante n'apporte ni preuve ni
        // contre-preuve : neutre, pour ne pas récompenser ni punir au hasard.
        if ($local === null || $remote === null) {
            return 0.5;
        }

        $localBrand = $this->normalizer->foldAccents(mb_strtolower($local));

        // Le champ distant liste parfois plusieurs marques (« Nivea, Beiersdorf »).
        foreach (explode(',', $this->normalizer->foldAccents(mb_strtolower($remote))) as $candidate) {
            $candidate = trim($candidate);

            if ($candidate === '') {
                continue;
            }

            if ($candidate === $localBrand || str_contains($candidate, $localBrand) || str_contains($localBrand, $candidate)) {
                return 1.0;
            }

            similar_text($localBrand, $candidate, $percent);

            if ($percent >= 85) {
                return 0.9;
            }
        }

        return 0.0;
    }

    private function quantityScore(?string $local, ?string $remote): float
    {
        if ($local === null || $remote === null) {
            return 0.5;
        }

        $remoteQuantity = $this->normalizer->normalize($remote)->quantity
            ?? $this->normalizeQuantityString($remote);

        if ($remoteQuantity === null) {
            return 0.5;
        }

        return $local === $remoteQuantity ? 1.0 : 0.0;
    }

    /**
     * Le champ `quantity` distant est libre (« 400ml », « 400 ML », « 40ml »).
     * Le normaliseur de noms sait déjà en extraire une contenance ; ce repli
     * couvre le cas où le champ ne contient que le nombre et l'unité.
     */
    private function normalizeQuantityString(string $quantity): ?string
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(ml|cl|l|kg|mg|g)\b/i', $quantity, $matches) !== 1) {
            return null;
        }

        return ProductNameNormalizer::formatQuantity($matches[1], $matches[2]);
    }
}
