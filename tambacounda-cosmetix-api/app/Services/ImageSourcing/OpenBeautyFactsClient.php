<?php

namespace App\Services\ImageSourcing;

use App\Services\ImageSourcing\Dto\RemoteProduct;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Accès en lecture à Open Beauty Facts.
 *
 * Deux règles du projet sont appliquées ici, pas ailleurs :
 *
 *  - un User-Agent qui identifie l'application et donne un contact ; une
 *    requête anonyme est traitée comme un robot et finit bloquée ;
 *  - les limites de débit publiées (15 lectures/minute, 10 recherches/minute
 *    par IP), que l'on respecte en espaçant les appels plutôt qu'en encaissant
 *    des 429.
 *
 * Le client ne connaît rien au score de confiance ni aux images : il rend des
 * RemoteProduct, ou une liste vide si la source est indisponible. Une source
 * externe en panne ne doit jamais faire échouer la commande.
 */
class OpenBeautyFactsClient
{
    /** Champs demandés à l'API : sans eux, une recherche renvoie ~25 Ko par produit. */
    private const FIELDS = 'code,product_name,brands,quantity,image_front_url,images';

    private float $lastProductRequestAt = 0.0;

    private float $lastSearchRequestAt = 0.0;

    private int $requestCount = 0;

    /** @var list<string> */
    private array $errors = [];

    public function __construct(private readonly ?\Closure $sleeper = null) {}

    /** Lecture d'un produit par son code-barres. Null si inconnu de la source. */
    public function findByBarcode(string $gtin): ?RemoteProduct
    {
        $this->throttle('product');

        $payload = $this->get("/api/v2/product/{$gtin}.json", ['fields' => self::FIELDS]);

        if ($payload === null || ($payload['status'] ?? 0) !== 1 || ! is_array($payload['product'] ?? null)) {
            return null;
        }

        return RemoteProduct::fromApi($payload['product'], $this->baseUrl());
    }

    /**
     * Recherche plein texte. L'API v2 ne sait pas la faire (elle ne filtre que
     * sur des attributs), donc on passe par cgi/search.pl, qui reste
     * l'endpoint plein texte disponible sur Open Beauty Facts.
     *
     * @return list<RemoteProduct>
     */
    public function search(string $query, int $limit): array
    {
        $this->throttle('search');

        $payload = $this->get('/cgi/search.pl', [
            'search_terms' => $query,
            'search_simple' => 1,
            'action' => 'process',
            'json' => 1,
            'page_size' => $limit,
            'fields' => self::FIELDS,
        ]);

        $products = $payload['products'] ?? null;

        if (! is_array($products)) {
            return [];
        }

        $results = [];

        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            $remote = RemoteProduct::fromApi($product, $this->baseUrl());

            if ($remote !== null) {
                $results[] = $remote;
            }
        }

        return $results;
    }

    /** Le binaire de l'image, ou null si le téléchargement échoue ou dépasse la taille permise. */
    public function download(string $url): ?string
    {
        $this->throttle('product');

        try {
            $response = $this->request()->withOptions(['stream' => false])->get($url);
        } catch (Throwable $exception) {
            $this->errors[] = "Téléchargement de {$url} : {$exception->getMessage()}";

            return null;
        }

        $this->requestCount++;

        if (! $response->successful()) {
            $this->errors[] = "Téléchargement de {$url} : HTTP {$response->status()}.";

            return null;
        }

        $body = $response->body();
        $maxBytes = (int) config('image_sourcing.image.max_download_bytes');

        if (strlen($body) > $maxBytes) {
            $this->errors[] = "Image ignorée, trop lourde à télécharger ({$url}).";

            return null;
        }

        return $body;
    }

    public function requestCount(): int
    {
        return $this->requestCount;
    }

    /** @return list<string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    private function get(string $path, array $query): ?array
    {
        try {
            $response = $this->request()->get($this->baseUrl().$path, $query);
        } catch (Throwable $exception) {
            $this->errors[] = "Appel à {$path} : {$exception->getMessage()}";

            return null;
        }

        $this->requestCount++;

        if (! $response->successful()) {
            $this->errors[] = "Appel à {$path} : HTTP {$response->status()}.";

            return null;
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : null;
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => (string) config('image_sourcing.open_beauty_facts.user_agent'),
            'Accept' => 'application/json',
        ])
            ->timeout((int) config('image_sourcing.open_beauty_facts.timeout'))
            // Le serveur public d'Open Beauty Facts est régulièrement lent à
            // accepter la connexion : les 10 secondes par défaut de Laravel
            // coupaient la moitié des appels avant même la requête.
            ->connectTimeout((int) config('image_sourcing.open_beauty_facts.connect_timeout'))
            ->retry(2, 2000, throw: false);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('image_sourcing.open_beauty_facts.base_url'), '/');
    }

    /**
     * Espace les appels pour rester sous la limite annoncée par la source.
     * On attend avant d'appeler, jamais après : le quota se mesure en requêtes
     * par minute glissante, et deux commandes lancées coup sur coup partagent
     * la même adresse IP.
     */
    private function throttle(string $bucket): void
    {
        $perMinute = (int) config("image_sourcing.open_beauty_facts.rate_limit.{$bucket}", 10);
        $minimumInterval = $perMinute > 0 ? 60 / $perMinute : 0.0;

        $last = $bucket === 'search' ? $this->lastSearchRequestAt : $this->lastProductRequestAt;
        $wait = $minimumInterval - (microtime(true) - $last);

        if ($wait > 0) {
            ($this->sleeper ?? static fn (float $seconds) => usleep((int) ($seconds * 1_000_000)))($wait);
        }

        $now = microtime(true);

        if ($bucket === 'search') {
            $this->lastSearchRequestAt = $now;
        } else {
            $this->lastProductRequestAt = $now;
        }
    }
}
