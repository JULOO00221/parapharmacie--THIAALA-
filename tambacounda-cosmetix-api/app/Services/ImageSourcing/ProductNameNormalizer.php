<?php

namespace App\Services\ImageSourcing;

use App\Models\Product;
use App\Services\ImageSourcing\Dto\NormalizedName;

/**
 * Remet en français lisible les libellés de caisse du catalogue importé.
 *
 * Les noms viennent d'un logiciel de point de vente : tout en majuscules,
 * tronqués à la volée, avec le conditionnement collé au nom
 * (« URIAGE HYSEAC 3-REGUL CRM/40ML (S) », « NIVEA DEMAQILLANT DOUX 125ML »).
 * Envoyés tels quels à un moteur de recherche externe, ils ne trouvent rien :
 * « CRM », « FL30ML » ou « (S) » n'apparaissent dans aucun nom de produit
 * public. On en extrait donc trois choses distinctes :
 *
 *   - le nom lisible, pour l'affichage au validateur ;
 *   - les mots significatifs, pour noter la ressemblance ;
 *   - la contenance, qui départage deux formats d'un même produit.
 */
class ProductNameNormalizer
{
    /**
     * Abréviations du catalogue vers leur forme complète. Les fautes de frappe
     * relevées à l'import (« DEMAQILLANT », « GENSIKIN », « PPOUX ») sont
     * traitées comme des abréviations : les corriger ici vaut mieux que
     * d'espérer qu'un moteur de recherche externe les devine.
     *
     * @var array<string, string>
     */
    private const ABBREVIATIONS = [
        'ant' => 'anti',
        'antipell' => 'antipelliculaire',
        'arg' => 'argan',
        'bb' => 'bebe',
        'chev' => 'cheveux',
        'chvx' => 'cheveux',
        'clai' => 'clair',
        'conc' => 'concentre',
        'concentr' => 'concentre',
        'corp' => 'corps',
        'cr' => 'creme',
        'cre' => 'creme',
        'crem' => 'creme',
        'crm' => 'creme',
        'demaq' => 'demaquillant',
        'demaqillant' => 'demaquillant',
        'deo' => 'deodorant',
        'dtf' => 'dentifrice',
        'ecl' => 'eclat',
        'edp' => 'eau de parfum',
        'edt' => 'eau de toilette',
        'flu' => 'fluide',
        'genc' => 'gencives',
        'gensikin' => 'genskin',
        'hle' => 'huile',
        'hlé' => 'huile',
        'huil' => 'huile',
        'hyd' => 'hydratant',
        'hydrat' => 'hydratant',
        'irri' => 'irritees',
        'kar' => 'karite',
        // Abréviation maison de La Roche-Posay : laissée telle quelle, elle
        // n'apparaît dans aucun nom commercial et plombe le score.
        'lrp' => 'la roche posay',
        'masq' => 'masque',
        'nett' => 'nettoyant',
        'nettoy' => 'nettoyant',
        'oliv' => 'olive',
        'pouss' => 'pousse',
        'ppoux' => 'poux',
        'prot' => 'protection',
        'protec' => 'protection',
        'puis' => 'puissance',
        'sav' => 'savon',
        'sens' => 'sensibles',
        'ser' => 'serum',
        'shamp' => 'shampooing',
        'shampg' => 'shampooing',
        'soin' => 'soin',
        'surg' => 'surgras',
        'toilett' => 'toilette',
        'trait' => 'traitement',
        'visag' => 'visage',
    ];

    /**
     * Mots de conditionnement et mots-outils : présents dans le libellé de
     * caisse, absents des noms commerciaux. Les garder ferait échouer toute
     * recherche, car le moteur externe exige que tous les mots correspondent.
     *
     * @var list<string>
     */
    private const NOISE = [
        'fl', 'flacon', 'frasco', 'bte', 'bt', 'boite', 'tube', 'pot', 'sh',
        'lot', 'pack', 'mini', 'de', 'du', 'des', 'le', 'la', 'les', 'l',
        'un', 'une', 'et', 'au', 'aux', 'a', 'en', 'the', 'd',
    ];

    public function forProduct(Product $product): NormalizedName
    {
        return $this->normalize($product->name, $product->brand?->name);
    }

    public function normalize(string $name, ?string $brandName = null): NormalizedName
    {
        $brand = $this->cleanBrand($brandName);

        $working = $this->foldAccents(mb_strtolower(trim($name)));
        $quantity = $this->extractQuantity($working);
        $working = $this->stripQuantities($working);
        $working = $this->stripPackagingMarkers($working);

        $tokens = $this->tokenize($working);
        $tokens = $this->expandAbbreviations($tokens);
        $tokens = $this->dropNoise($tokens);
        $tokens = $this->dropBrandTokens($tokens, $brand);

        return new NormalizedName(
            text: $this->readableText($tokens),
            tokens: $tokens,
            brand: $brand,
            quantity: $quantity,
        );
    }

    /**
     * Le catalogue utilise des marques de remplacement pour les articles sans
     * marque — « Autre », « (accessoire, sans marque) », « (générique parfum) ».
     * Chercher dessus ne ramènerait que du bruit, donc on les considère absentes.
     * Même convention que isRealBrand() côté frontend.
     */
    public function cleanBrand(?string $brandName): ?string
    {
        $brand = trim((string) $brandName);

        if ($brand === '' || mb_strtolower($brand) === 'autre' || str_starts_with($brand, '(')) {
            return null;
        }

        // « IAP (parfums génériques) » → « IAP » : la parenthèse est une note
        // de classement interne, pas une partie du nom de la marque.
        $brand = trim((string) preg_replace('/\s*\([^)]*\)\s*/u', ' ', $brand));

        return $brand === '' ? null : $brand;
    }

    /** Contenance normalisée (« FL500ML », « /400ML », « T/100G » → « 500 ml », « 400 ml », « 100 g »). */
    private function extractQuantity(string $text): ?string
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(mml|ml|cl|l|kg|mg|g)\b/u', $text, $matches) !== 1) {
            return null;
        }

        // « 125MML » est une coquille récurrente du catalogue pour « 125ML ».
        $unit = $matches[2] === 'mml' ? 'ml' : $matches[2];

        return self::formatQuantity($matches[1], $unit);
    }

    /**
     * Met une contenance sous une forme unique et comparable (« 1,50 L » et
     * « 1.5l » → « 1.5 l »). Les zéros ne sont retirés qu'après une virgule :
     * les retirer partout transformerait « 500 ml » en « 5 ml ».
     */
    public static function formatQuantity(string $value, string $unit): string
    {
        $value = str_replace(',', '.', $value);

        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return $value.' '.mb_strtolower($unit);
    }

    private function stripQuantities(string $text): string
    {
        return (string) preg_replace('/\d+(?:[.,]\d+)?\s*(mml|ml|cl|l|kg|mg|g)\b/u', ' ', $text);
    }

    /**
     * Retire ce qui n'appartient pas au nom commercial : les mentions de
     * classement entre parenthèses — « (S) », « (D) », « (S-U) » —, les
     * numéros de référence de parfum (« N°15 », « Nø32 ») et la ponctuation
     * de conditionnement (« CRM/40ML », « •CLAI »).
     */
    private function stripPackagingMarkers(string $text): string
    {
        $text = (string) preg_replace('/\([^)]*\)/u', ' ', $text);
        $text = (string) preg_replace('/n[o°ø]\s*\d+/u', ' ', $text);
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** @return list<string> */
    private function tokenize(string $text): array
    {
        return array_values(array_filter(explode(' ', $text), static fn (string $token): bool => $token !== ''));
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function expandAbbreviations(array $tokens): array
    {
        $expanded = [];

        foreach ($tokens as $token) {
            $replacement = self::ABBREVIATIONS[$token] ?? $token;

            // Une abréviation peut valoir plusieurs mots (« edp » → « eau de parfum »).
            foreach (explode(' ', $replacement) as $word) {
                $expanded[] = $word;
            }
        }

        return $expanded;
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function dropNoise(array $tokens): array
    {
        $kept = [];

        foreach ($tokens as $token) {
            // Un mot d'une seule lettre ou un nombre isolé ne discrimine rien.
            if (in_array($token, self::NOISE, true) || mb_strlen($token) < 2 || ctype_digit($token)) {
                continue;
            }

            if (! in_array($token, $kept, true)) {
                $kept[] = $token;
            }
        }

        return $kept;
    }

    /**
     * La marque est presque toujours répétée en tête du nom
     * (« NIVEA DEMAQILLANT DOUX »). Elle est portée séparément par la requête,
     * donc la laisser dans les mots-clés fausserait le score de ressemblance :
     * un produit de la même marque paraîtrait proche quel que soit son nom.
     *
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function dropBrandTokens(array $tokens, ?string $brand): array
    {
        if ($brand === null) {
            return $tokens;
        }

        $brandTokens = $this->tokenize($this->stripPackagingMarkers($this->foldAccents(mb_strtolower($brand))));
        $stripped = array_values(array_filter(
            $tokens,
            static fn (string $token): bool => ! in_array($token, $brandTokens, true),
        ));

        // Un produit dont le nom se réduit à sa marque (« FRANCOISE BEDON »)
        // doit garder de quoi être cherché.
        return $stripped === [] ? $tokens : $stripped;
    }

    /** @param list<string> $tokens */
    private function readableText(array $tokens): string
    {
        return implode(' ', $tokens);
    }

    /**
     * Comparer « Hyséac » et « HYSEAC » impose de ramener les deux à la même
     * forme : les accents sont perdus à l'export du logiciel de caisse.
     */
    public function foldAccents(string $text): string
    {
        // Les puces et symboles du libellé de caisse (« •CLAI ») doivent
        // disparaître avant la translittération : iconv les rapprocherait
        // d'une lettre, ce qui créerait un mot inexistant (« oclai »).
        $text = (string) preg_replace('/[^\p{L}\p{N}\s\x20-\x7E]/u', ' ', $text);

        $folded = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        if ($folded === false) {
            return $text;
        }

        // iconv rend « é » sous la forme « 'e » selon la locale : on ne garde
        // que la lettre.
        return (string) preg_replace('/[\'`^"~]/', '', $folded);
    }
}
