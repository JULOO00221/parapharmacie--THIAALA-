<?php

namespace Tests\Unit\ImageSourcing;

use App\Services\ImageSourcing\ProductNameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Les cas de ce test sont de vrais libellés du catalogue importé, pas des
 * exemples inventés : c'est la seule façon de savoir si le nettoyage tient
 * face à ce que produit réellement le logiciel de caisse.
 */
class ProductNameNormalizerTest extends TestCase
{
    private ProductNameNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new ProductNameNormalizer;
    }

    /** @return array<string, array{0: string, 1: string|null, 2: list<string>}> */
    public static function labels(): array
    {
        return [
            'abréviations développées' => [
                'URIAGE HYSEAC 3-REGUL CRM/40ML (S)', 'Uriage', ['hyseac', 'regul', 'creme'],
            ],
            'faute de frappe du catalogue' => [
                'NIVEA DEMAQILLANT DOUX 125ML', 'Nivea', ['demaquillant', 'doux'],
            ],
            'mention de conditionnement écartée' => [
                'MIXA CREME CICA REPAIR POT 400ML', 'Mixa', ['creme', 'cica', 'repair'],
            ],
            'puce typographique écartée' => [
                'DEPIWHITE (S-U) LAIT CORPS •CLAI 500ML', 'Depiwhite', ['lait', 'corps', 'clair'],
            ],
            'numéro de référence de parfum écarté' => [
                'IAP EDP (D) Nø32 OLYMPEA (MINI) 50ML', 'IAP (parfums génériques)', ['eau', 'parfum', 'olympea'],
            ],
            'nom réduit à la marque : les mots sont conservés' => [
                'FRANCOISE BEDON LAIT ECL PUISSANCE', 'Françoise Bedon', ['lait', 'eclat', 'puissance'],
            ],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('labels')]
    public function test_it_reduces_a_till_label_to_its_meaningful_words(string $name, ?string $brand, array $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize($name, $brand)->tokens);
    }

    /** @return array<string, array{0: string, 1: string|null}> */
    public static function quantities(): array
    {
        return [
            'collée au nom' => ['NIVEA DEMAQILLANT DOUX 125ML', '125 ml'],
            'préfixée du conditionnement' => ['ELUDRILPRO BAIN BOUCHE FL500ML', '500 ml'],
            'séparée par une barre oblique' => ['DUCRAY (S) KERACNYL GEL/400ML', '400 ml'],
            'en grammes' => ['GLOWMAX CR T/100G', '100 g'],
            'coquille MML du catalogue' => ['DAM NATUR HUIL POUSS CHEVEUX 125MML', '125 ml'],
            'absente' => ['FRANCOISE BEDON LAIT ECL PUISSANCE', null],
        ];
    }

    #[DataProvider('quantities')]
    public function test_it_extracts_the_package_size(string $name, ?string $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize($name)->quantity);
    }

    /**
     * Le bug qui a motivé ce test : retirer les zéros de fin transformait
     * « 500 ml » en « 5 ml », et donc comparait un flacon de 500 ml à un
     * échantillon.
     */
    public function test_it_never_strips_trailing_zeros_from_a_whole_number(): void
    {
        $this->assertSame('500 ml', $this->normalizer->normalize('SAVON 500ML')->quantity);
        $this->assertSame('1.5 l', $this->normalizer->normalize('EAU 1,50L')->quantity);
    }

    public function test_it_ignores_placeholder_brands(): void
    {
        $this->assertNull($this->normalizer->cleanBrand('Autre'));
        $this->assertNull($this->normalizer->cleanBrand('(accessoire, sans marque)'));
        $this->assertNull($this->normalizer->cleanBrand(null));
        $this->assertSame('IAP', $this->normalizer->cleanBrand('IAP (parfums génériques)'));
        $this->assertSame('Nivea', $this->normalizer->cleanBrand('  Nivea '));
    }

    public function test_search_queries_go_from_precise_to_broad(): void
    {
        $queries = $this->normalizer
            ->normalize('MARIE ROSE SHAMP ANTI PPOUX&LENTE 125ML', 'Marie Rose')
            ->searchQueries();

        $this->assertSame([
            'marie rose shampooing anti poux lente',
            'marie rose shampooing anti',
            'marie rose',
        ], $queries);
    }

    public function test_a_product_without_a_usable_name_produces_no_query(): void
    {
        $this->assertSame([], $this->normalizer->normalize('500ML')->searchQueries());
    }
}
