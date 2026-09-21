<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ImageSourcing\Dto\SourcingOutcome;
use App\Services\ImageSourcing\OpenBeautyFactsClient;
use App\Services\ImageSourcing\ProductImageSourcingService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Cherche sur Open Beauty Facts une photo pour les produits qui n'en ont pas.
 *
 * Aucune photo n'est publiée : la commande ne produit que des propositions à
 * valider dans le back-office (Photos à valider). Elle rend en fin de course
 * un CSV des produits restés sans correspondance, groupés par marque, pour
 * savoir quelles séances photo organiser en boutique.
 *
 * La source est publique et limitée en débit : compter environ huit secondes
 * par produit. Traiter tout le catalogue prend donc un peu plus d'une heure,
 * et la commande est faite pour être relancée — elle reprend là où elle en
 * est, sans reproposer ce qui a déjà été vu.
 */
class SourceProductImagesCommand extends Command
{
    protected $signature = 'products:source-images
        {--limit=20 : Nombre de produits à traiter (0 = tous)}
        {--offset=0 : Nombre de produits à passer, pour avancer lot par lot}
        {--dry-run : Ne télécharge rien et n\'écrit rien : affiche seulement ce qui serait proposé}
        {--brand=* : Ne traiter que les produits de ces marques (nom ou identifiant, répétable)}
        {--min-confidence= : Score minimal pour retenir une correspondance (défaut : configuration)}
        {--csv= : Chemin du rapport CSV des produits sans correspondance}';

    protected $description = 'Cherche des photos produit sur Open Beauty Facts et les met en attente de validation.';

    public function handle(ProductImageSourcingService $sourcing, OpenBeautyFactsClient $client): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $minConfidence = (int) ($this->option('min-confidence') ?? config('image_sourcing.matching.min_confidence'));
        $products = $this->products();

        if ($products->isEmpty()) {
            $this->info('Aucun produit sans photo à traiter.');

            return self::SUCCESS;
        }

        $this->intro($products->count(), $dryRun, $minConfidence);

        /** @var Collection<int, SourcingOutcome> $outcomes */
        $outcomes = new Collection;
        $progress = $this->output->createProgressBar($products->count());
        $progress->start();

        foreach ($products as $product) {
            $outcomes->push($sourcing->sourceFor($product, $dryRun, $minConfidence));
            $progress->advance();
        }

        $progress->finish();
        $this->newLine(2);

        $this->renderMatches($outcomes);
        $this->renderSummary($outcomes, $client, $dryRun);

        $path = $this->writeUnmatchedCsv($outcomes);

        if ($path !== null) {
            $this->newLine();
            $this->line("Produits sans correspondance : <info>{$path}</info>");
        }

        return self::SUCCESS;
    }

    /** @return Collection<int, Product> */
    private function products(): Collection
    {
        $query = Product::query()
            ->with('brand')
            ->whereDoesntHave('images')
            ->orderBy('id');

        /** @var list<string> $brands */
        $brands = array_filter((array) $this->option('brand'), static fn (string $brand): bool => trim($brand) !== '');

        if ($brands !== []) {
            $query->whereHas('brand', function (Builder $builder) use ($brands): void {
                $ids = array_map('intval', array_filter($brands, 'is_numeric'));
                $names = array_map(
                    static fn (string $brand): string => mb_strtolower(trim($brand)),
                    array_filter($brands, static fn (string $brand): bool => ! is_numeric($brand)),
                );

                $builder->where(function (Builder $scoped) use ($ids, $names): void {
                    if ($ids !== []) {
                        $scoped->orWhereIn('id', $ids);
                    }

                    if ($names !== []) {
                        $scoped->orWhereIn(DB::raw('lower(name)'), $names);
                    }
                });
            });
        }

        // Un produit sans correspondance ne laisse aucune trace en base : sans
        // --offset, relancer avec --limit retomberait indéfiniment sur les
        // mêmes premiers produits au lieu d'avancer dans le catalogue.
        $offset = (int) $this->option('offset');

        if ($offset > 0) {
            $query->skip($offset);
        }

        $limit = (int) $this->option('limit');

        return $limit > 0 ? $query->limit($limit)->get() : $query->get();
    }

    private function intro(int $count, bool $dryRun, int $minConfidence): void
    {
        $this->newLine();
        $this->line("<info>{$count}</info> produit(s) sans photo à traiter.");
        $this->line('Source : Open Beauty Facts — photos sous licence '
            .config('image_sourcing.open_beauty_facts.image_license.code').'.');
        $this->line("Score minimal retenu : <info>{$minConfidence}</info>/100.");

        if ($dryRun) {
            $this->warn('Simulation (--dry-run) : aucune image téléchargée, aucune écriture en base.');
        }

        $this->newLine();
    }

    /** @param Collection<int, SourcingOutcome> $outcomes */
    private function renderMatches(Collection $outcomes): void
    {
        $matches = $outcomes->filter(fn (SourcingOutcome $outcome): bool => $outcome->isMatch())
            ->sortByDesc(fn (SourcingOutcome $outcome): int => (int) $outcome->confidence);

        if ($matches->isEmpty()) {
            $this->warn('Aucune correspondance retenue sur ce lot.');

            return;
        }

        $this->line('<comment>Correspondances proposées (à valider dans le back-office)</comment>');

        $this->table(
            ['Score', 'Produit du catalogue', 'Photo trouvée', 'Requête', 'Détail'],
            $matches->map(fn (SourcingOutcome $outcome): array => [
                $outcome->confidence.'/100',
                $this->shorten($outcome->product->name, 42),
                $this->shorten((string) $outcome->matchedName, 42),
                $this->shorten((string) $outcome->searchQuery, 24),
                $outcome->status === SourcingOutcome::ALREADY_PENDING
                    ? 'déjà en attente'
                    : (string) $outcome->detail,
            ])->all(),
        );
    }

    /** @param Collection<int, SourcingOutcome> $outcomes */
    private function renderSummary(Collection $outcomes, OpenBeautyFactsClient $client, bool $dryRun): void
    {
        $this->newLine();
        $this->line('<comment>Répartition</comment>');

        $this->table(
            ['Résultat', 'Produits'],
            $outcomes->countBy(fn (SourcingOutcome $outcome): string => $outcome->status)
                ->sortDesc()
                ->map(fn (int $count, string $status): array => [$status, $count])
                ->values()
                ->all(),
        );

        $this->line("Requêtes envoyées à la source : <info>{$client->requestCount()}</info>.");

        $retryable = $outcomes->filter(fn (SourcingOutcome $outcome): bool => $outcome->isRetryable())->count();

        if ($retryable > 0) {
            $this->warn(
                "{$retryable} produit(s) ont échoué à cause de la source, pas du catalogue : "
                .'relancer la commande les retentera.'
            );
        }

        foreach (array_slice($client->errors(), 0, 5) as $error) {
            $this->line("  <fg=gray>{$error}</>");
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Simulation terminée : rien n\'a été téléchargé ni enregistré.');
        }
    }

    /**
     * CSV des produits restés sans photo, groupés par marque et triés par
     * nombre décroissant : la marque qui coûte le plus de photos apparaît en
     * premier, c'est elle qu'il faut shooter en priorité.
     *
     * @param  Collection<int, SourcingOutcome>  $outcomes
     */
    private function writeUnmatchedCsv(Collection $outcomes): ?string
    {
        $unmatched = $outcomes->filter(fn (SourcingOutcome $outcome): bool => $outcome->isUnmatched());

        if ($unmatched->isEmpty()) {
            return null;
        }

        $path = (string) ($this->option('csv')
            ?? storage_path('app/rapports/produits-sans-photo-'.now()->format('Y-m-d_His').'.csv'));

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, recursive: true);
        }

        $groups = $unmatched
            ->groupBy(fn (SourcingOutcome $outcome): string => $outcome->product->brand?->name ?? 'Sans marque')
            ->sortByDesc(fn (Collection $group): int => $group->count());

        $handle = fopen($path, 'wb');

        // BOM UTF-8 : sans lui, Excel affiche « crème » au lieu de « crème ».
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'marque', 'produits_sans_photo_pour_la_marque', 'sku', 'produit',
            'code_barres', 'raison', 'meilleur_score', 'photo_examinee', 'requete',
        ]);

        foreach ($groups as $brand => $group) {
            foreach ($group as $outcome) {
                fputcsv($handle, [
                    $brand,
                    $group->count(),
                    $outcome->product->sku,
                    $outcome->product->name,
                    $outcome->product->barcode,
                    $outcome->status,
                    $outcome->confidence !== null ? $outcome->confidence.'/100' : '',
                    $outcome->matchedName,
                    $outcome->searchQuery,
                ]);
            }
        }

        fclose($handle);

        $this->newLine();
        $this->line('<comment>Marques les plus concernées</comment>');
        $this->table(
            ['Marque', 'Produits sans photo'],
            $groups->take(10)->map(fn (Collection $group, string $brand): array => [$brand, $group->count()])->values()->all(),
        );

        return $path;
    }

    private function shorten(string $text, int $length): string
    {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1).'…' : $text;
    }
}
