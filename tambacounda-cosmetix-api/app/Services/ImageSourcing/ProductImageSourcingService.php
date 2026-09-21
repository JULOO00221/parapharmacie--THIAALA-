<?php

namespace App\Services\ImageSourcing;

use App\Models\Product;
use App\Models\ProductImageCandidate;
use App\Services\ImageSourcing\Dto\NormalizedName;
use App\Services\ImageSourcing\Dto\RemoteProduct;
use App\Services\ImageSourcing\Dto\SourcingOutcome;
use App\Services\StorageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cherche une photo pour un produit du catalogue et l'enregistre **en attente
 * de validation**.
 *
 * Règle non négociable du projet : ce service ne crée jamais de ProductImage
 * et ne touche jamais à une fiche produit. Il ne produit que des
 * ProductImageCandidate, invisibles de la boutique. La publication est un
 * acte humain, réalisé depuis le back-office (voir ApproveImageCandidate).
 *
 * Stratégie de recherche :
 *   1. par code-barres, uniquement si c'en est vraiment un (GTIN valide) ;
 *   2. sinon par marque + mots-clés du nom, du libellé le plus précis au plus
 *      large, en s'arrêtant au premier qui ramène quelque chose.
 */
class ProductImageSourcingService
{
    public function __construct(
        private readonly OpenBeautyFactsClient $client,
        private readonly ProductNameNormalizer $normalizer,
        private readonly MatchScorer $scorer,
        private readonly ImageProcessor $processor,
        private readonly StorageService $storage,
    ) {}

    public function sourceFor(Product $product, bool $dryRun, int $minConfidence): SourcingOutcome
    {
        $existing = ProductImageCandidate::query()
            ->where('product_id', $product->id)
            ->pending()
            ->first();

        // Une proposition déjà en attente ne doit pas être écrasée : le
        // validateur l'a peut-être sous les yeux. Relancer la commande est donc
        // sans effet de bord sur ce produit.
        if ($existing !== null) {
            return SourcingOutcome::make(
                $product,
                SourcingOutcome::ALREADY_PENDING,
                confidence: $existing->confidence,
                matchedName: $existing->source_product_name,
            );
        }

        $normalized = $this->normalizer->forProduct($product);

        if (Gtin::normalize($product->barcode) === null && $normalized->searchQueries() === []) {
            return SourcingOutcome::make($product, SourcingOutcome::NOT_SEARCHABLE);
        }

        $errorsBefore = count($this->client->errors());

        [$remote, $score, $breakdown, $method, $query] = $this->bestMatch($product, $normalized);

        if ($remote === null) {
            // Distinguer « la source n'a rien » de « la source n'a pas répondu » :
            // seul le second cas justifie de relancer la commande plus tard.
            $failed = count($this->client->errors()) > $errorsBefore;

            return SourcingOutcome::make(
                $product,
                $failed ? SourcingOutcome::SOURCE_UNAVAILABLE : SourcingOutcome::NO_RESULT,
                searchQuery: $query,
            );
        }

        if ($remote->imageUrl === null) {
            return SourcingOutcome::make(
                $product,
                SourcingOutcome::NO_PHOTO,
                confidence: $score,
                matchedName: $remote->name,
                searchQuery: $query,
            );
        }

        if ($score < $minConfidence) {
            return SourcingOutcome::make(
                $product,
                SourcingOutcome::LOW_CONFIDENCE,
                confidence: $score,
                matchedName: $remote->name,
                searchQuery: $query,
            );
        }

        // Une photo déjà refusée pour ce produit ne revient pas : une décision
        // humaine ne se fait pas défaire par une relance de la commande.
        $rejected = ProductImageCandidate::query()
            ->where('product_id', $product->id)
            ->where('source_image_url', $remote->imageUrl)
            ->where('status', ProductImageCandidate::STATUS_REJECTED)
            ->exists();

        if ($rejected) {
            return SourcingOutcome::make(
                $product,
                SourcingOutcome::ALREADY_REJECTED,
                confidence: $score,
                matchedName: $remote->name,
                searchQuery: $query,
            );
        }

        return $this->recordCandidate($product, $remote, $score, $breakdown, $method, $query, $dryRun);
    }

    /**
     * @return array{0: RemoteProduct|null, 1: int, 2: array<string, mixed>, 3: string, 4: string|null}
     */
    private function bestMatch(Product $product, NormalizedName $normalized): array
    {
        $gtin = Gtin::normalize($product->barcode);

        if ($gtin !== null) {
            $remote = $this->client->findByBarcode($gtin);

            if ($remote !== null) {
                return [
                    $remote,
                    $this->scorer->barcodeScore(),
                    ['methode' => 'code-barres GTIN '.$gtin],
                    ProductImageCandidate::METHOD_BARCODE,
                    $gtin,
                ];
            }
        }

        $limit = (int) config('image_sourcing.matching.candidates_per_search');
        $best = null;
        $bestScore = -1;
        $bestBreakdown = [];
        $usedQuery = null;

        foreach ($normalized->searchQueries() as $query) {
            $results = $this->client->search($query, $limit);

            if ($results === []) {
                continue;
            }

            foreach ($results as $remote) {
                // Une fiche sans photo ne sert à rien ici, quelle que soit sa note.
                if ($remote->imageUrl === null) {
                    continue;
                }

                ['score' => $score, 'breakdown' => $breakdown] = $this->scorer->score($normalized, $remote);

                if ($score > $bestScore) {
                    [$best, $bestScore, $bestBreakdown, $usedQuery] = [$remote, $score, $breakdown, $query];
                }
            }

            // La requête a ramené des résultats : les libellés plus courts qui
            // suivent seraient plus vagues, inutile de dépenser du quota.
            if ($best !== null) {
                break;
            }
        }

        return [$best, max(0, $bestScore), $bestBreakdown, ProductImageCandidate::METHOD_BRAND_NAME, $usedQuery];
    }

    /**
     * @param  array<string, mixed>  $breakdown
     */
    private function recordCandidate(
        Product $product,
        RemoteProduct $remote,
        int $score,
        array $breakdown,
        string $method,
        ?string $query,
        bool $dryRun,
    ): SourcingOutcome {
        $attributes = [
            'source' => 'open_beauty_facts',
            'source_code' => $remote->code,
            'source_page_url' => $remote->pageUrl,
            'source_product_name' => $remote->name,
            'source_brand' => $remote->brands,
            'source_quantity' => $remote->quantity,
            'license_code' => (string) config('image_sourcing.open_beauty_facts.image_license.code'),
            'license_url' => (string) config('image_sourcing.open_beauty_facts.image_license.url'),
            'attribution' => $this->attributionFor($remote),
            'match_method' => $method,
            'search_query' => $query,
            'confidence' => $score,
            'confidence_breakdown' => $breakdown,
            'status' => ProductImageCandidate::STATUS_PENDING,
        ];

        // En simulation, on s'arrête avant le réseau : aucun octet téléchargé,
        // aucun fichier écrit, rien en base. Seul le rapport est produit.
        if ($dryRun) {
            return SourcingOutcome::make(
                $product,
                SourcingOutcome::CANDIDATE,
                confidence: $score,
                matchedName: $remote->name,
                searchQuery: $query,
                detail: 'simulation : photo non téléchargée',
            );
        }

        $binary = $this->client->download((string) $remote->imageUrl);

        if ($binary === null) {
            return SourcingOutcome::make(
                $product,
                SourcingOutcome::DOWNLOAD_FAILED,
                confidence: $score,
                matchedName: $remote->name,
                searchQuery: $query,
            );
        }

        $processed = $this->processor->process($binary);

        if ($processed === null) {
            return SourcingOutcome::make(
                $product,
                SourcingOutcome::IMAGE_UNUSABLE,
                confidence: $score,
                matchedName: $remote->name,
                searchQuery: $query,
                detail: 'image illisible, minuscule, ou trop allongée pour être une photo de produit',
            );
        }

        if ($processed->overBudget) {
            return SourcingOutcome::make(
                $product,
                SourcingOutcome::IMAGE_TOO_HEAVY,
                confidence: $score,
                matchedName: $remote->name,
                searchQuery: $query,
                detail: "reste à {$processed->humanSize()} après compression maximale",
            );
        }

        $path = sprintf(
            '%s/%s-%s.webp',
            trim((string) config('image_sourcing.image.candidate_directory'), '/'),
            $product->id,
            Str::random(12),
        );

        $this->storage->filesystem()->put($path, $processed->binary);

        DB::transaction(function () use ($product, $remote, $attributes, $path, $processed): void {
            ProductImageCandidate::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'source_image_url' => (string) $remote->imageUrl,
                ],
                $attributes + [
                    'path' => $path,
                    'width' => $processed->width,
                    'height' => $processed->height,
                    'bytes' => $processed->bytes,
                ],
            );
        });

        return SourcingOutcome::make(
            $product,
            SourcingOutcome::CANDIDATE,
            confidence: $score,
            matchedName: $remote->name,
            searchQuery: $query,
            detail: "{$processed->width}×{$processed->height} px, {$processed->humanSize()}",
        );
    }

    /**
     * Texte d'attribution imposé par la licence CC-BY-SA : auteur de la photo,
     * source, licence. Il est figé au moment de la découverte et stocké tel
     * quel, pour rester affichable même si la fiche distante change ensuite.
     */
    private function attributionFor(RemoteProduct $remote): string
    {
        $author = $remote->photographer !== null
            ? $remote->photographer
            : 'les contributeurs Open Beauty Facts';

        $license = (string) config('image_sourcing.open_beauty_facts.image_license.code');

        return "Photo : {$author} — Open Beauty Facts ({$license})";
    }
}
