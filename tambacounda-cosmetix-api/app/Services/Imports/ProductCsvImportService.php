<?php

namespace App\Services\Imports;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Tag;
use App\Services\Imports\Dto\ImportReport;
use App\Services\Imports\Dto\ImportRowResult;
use App\Services\ProductService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Orchestrates the whole CSV import: parsing, column mapping analysis,
 * a read-only preview, and the real transactional import. preview() and
 * import() both go through run(), with a $dryRun flag, so the preview is
 * guaranteed to reflect exactly what a real import would do — there is
 * only one code path that decides row outcomes.
 */
class ProductCsvImportService
{
    private const CHUNK_SIZE = 200;

    public function __construct(private readonly ProductService $productService)
    {
    }

    /**
     * @return array{row_count: int, headers: array<int, string>, mapping: array<string, int|null>, unmapped_header_indexes: array<int, int>, missing_required: array<int, string>, delimiter: string}
     */
    public function analyze(string $absolutePath): array
    {
        $parsed = (new ProductCsvParser())->parse($absolutePath);
        $mapping = ProductColumnMapper::guessMapping($parsed['headers']);

        return [
            'row_count' => count($parsed['rows']),
            'headers' => $parsed['headers'],
            'mapping' => $mapping,
            'unmapped_header_indexes' => ProductColumnMapper::unmappedHeaderIndexes($parsed['headers'], $mapping),
            'missing_required' => ProductColumnMapper::missingRequiredFields($mapping),
            'delimiter' => $parsed['delimiter'],
        ];
    }

    /**
     * @param  array<string, int|null>  $mapping
     * @param  array{category: string, brand: string, tag: string}  $policies
     * @return array{preview_rows: array<int, ImportRowResult>, report: ImportReport}
     */
    public function preview(string $absolutePath, array $mapping, Store $store, array $policies, int $limit = 50): array
    {
        $report = $this->run($absolutePath, $mapping, $store, $policies, dryRun: true);

        return [
            'preview_rows' => array_slice($report->rows, 0, $limit),
            'report' => $report,
        ];
    }

    /**
     * @param  array<string, int|null>  $mapping
     * @param  array{category: string, brand: string, tag: string}  $policies
     */
    public function import(string $absolutePath, array $mapping, Store $store, array $policies): ImportReport
    {
        return $this->run($absolutePath, $mapping, $store, $policies, dryRun: false);
    }

    /**
     * @param  array<string, int|null>  $mapping
     * @param  array{category: string, brand: string, tag: string}  $policies
     */
    private function run(string $absolutePath, array $mapping, Store $store, array $policies, bool $dryRun): ImportReport
    {
        $parsed = (new ProductCsvParser())->parse($absolutePath);

        $extracted = [];

        foreach ($parsed['rows'] as $index => $record) {
            $rowNumber = $index + 2; // header is line 1, data starts at line 2
            $extracted[$rowNumber] = $this->extractFields($record, $mapping);
        }

        $skuFirstRow = [];
        $allCategoryNames = [];
        $allBrandNames = [];

        foreach ($extracted as $rowNumber => $fields) {
            if ($fields['sku'] !== null && ! isset($skuFirstRow[$fields['sku']])) {
                $skuFirstRow[$fields['sku']] = $rowNumber;
            }

            if ($fields['category'] !== null) {
                $allCategoryNames[] = $fields['category'];
            }

            if ($fields['subcategory'] !== null) {
                $allCategoryNames[] = $fields['subcategory'];
            }

            if ($fields['brand'] !== null) {
                $allBrandNames[] = $fields['brand'];
            }
        }

        $allSkus = array_values(array_unique(array_filter(array_map(
            fn (array $f) => $f['sku'],
            $extracted
        ))));

        $existingProducts = Product::whereIn('sku', $allSkus)->get()->keyBy('sku');

        $categoriesBySlug = ProductCategory::whereIn(
            'slug',
            array_map(fn (string $n) => Str::slug($n), array_unique($allCategoryNames))
        )->get()->keyBy('slug')->all();

        $brandsBySlug = Brand::whereIn(
            'slug',
            array_map(fn (string $n) => Str::slug($n), array_unique($allBrandNames))
        )->get()->keyBy('slug')->all();

        $tagsBySlug = Tag::all()->keyBy('slug')->all();

        $existingStockByProductId = Stock::where('store_id', $store->id)
            ->whereIn('product_id', $existingProducts->pluck('id'))
            ->get()
            ->keyBy('product_id')
            ->all();

        $stats = ['categoriesCreated' => 0, 'brandsCreated' => 0, 'tagsCreated' => 0];
        $rows = [];
        $created = 0;
        $updated = 0;
        $duplicates = 0;
        $skipped = 0;
        $errors = 0;

        foreach (array_chunk($extracted, self::CHUNK_SIZE, true) as $chunk) {
            foreach ($chunk as $rowNumber => $fields) {
                $result = $this->processRow(
                    $rowNumber,
                    $fields,
                    $store,
                    $policies,
                    $dryRun,
                    $skuFirstRow,
                    $existingProducts,
                    $categoriesBySlug,
                    $brandsBySlug,
                    $tagsBySlug,
                    $existingStockByProductId,
                    $stats,
                );

                $rows[] = $result;

                match ($result->status) {
                    ImportRowResult::STATUS_NEW => $created++,
                    ImportRowResult::STATUS_UPDATED => $updated++,
                    ImportRowResult::STATUS_DUPLICATE => $duplicates++,
                    ImportRowResult::STATUS_SKIPPED => $skipped++,
                    ImportRowResult::STATUS_ERROR => $errors++,
                    default => null,
                };
            }
        }

        return new ImportReport(
            totalRows: count($extracted),
            created: $created,
            updated: $updated,
            duplicates: $duplicates,
            skipped: $skipped,
            errors: $errors,
            categoriesCreated: $stats['categoriesCreated'],
            brandsCreated: $stats['brandsCreated'],
            tagsCreated: $stats['tagsCreated'],
            rows: $rows,
        );
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array{category: string, brand: string, tag: string}  $policies
     * @param  array<string, int>  $skuFirstRow
     * @param  \Illuminate\Support\Collection<string, Product>  $existingProducts
     * @param  array<string, mixed>  $categoriesBySlug
     * @param  array<string, mixed>  $brandsBySlug
     * @param  array<string, mixed>  $tagsBySlug
     * @param  array<int, Stock>  $existingStockByProductId
     * @param  array{categoriesCreated: int, brandsCreated: int, tagsCreated: int}  $stats
     */
    private function processRow(
        int $rowNumber,
        array $fields,
        Store $store,
        array $policies,
        bool $dryRun,
        array $skuFirstRow,
        $existingProducts,
        array &$categoriesBySlug,
        array &$brandsBySlug,
        array &$tagsBySlug,
        array $existingStockByProductId,
        array &$stats,
    ): ImportRowResult {
        $sku = $fields['sku'];

        if ($sku === null) {
            return new ImportRowResult(
                rowNumber: $rowNumber,
                status: ImportRowResult::STATUS_ERROR,
                errors: ['SKU manquant.'],
                raw: $fields,
            );
        }

        if ($skuFirstRow[$sku] !== $rowNumber) {
            return new ImportRowResult(
                rowNumber: $rowNumber,
                status: ImportRowResult::STATUS_DUPLICATE,
                sku: $sku,
                warnings: [sprintf('SKU déjà rencontré à la ligne %d (première occurrence conservée).', $skuFirstRow[$sku])],
                raw: $fields,
            );
        }

        /** @var Product|null $existingProduct */
        $existingProduct = $existingProducts->get($sku);
        $isUpdate = $existingProduct !== null;

        $errors = [];
        $warnings = [];

        if (! $isUpdate && $fields['name'] === null) {
            $errors[] = 'Nom manquant pour un nouveau produit.';
        }

        $price = null;

        if ($fields['price'] !== null) {
            $price = $this->parseDecimal($fields['price']);

            if ($price === null || $price < 0) {
                $errors[] = 'Prix invalide.';
            }
        } elseif (! $isUpdate) {
            $errors[] = 'Prix manquant pour un nouveau produit.';
        }

        if ($errors !== []) {
            return new ImportRowResult(
                rowNumber: $rowNumber,
                status: ImportRowResult::STATUS_ERROR,
                sku: $sku,
                errors: $errors,
                raw: $fields,
            );
        }

        if (! $isUpdate && $fields['category'] === null) {
            return new ImportRowResult(
                rowNumber: $rowNumber,
                status: ImportRowResult::STATUS_ERROR,
                sku: $sku,
                errors: ['Catégorie manquante pour un nouveau produit.'],
                raw: $fields,
            );
        }

        [$categoryId, $categoryWillExist, $categoryCreated] = $this->resolveReference(
            $fields['category'],
            $categoriesBySlug,
            $policies['category'] ?? 'create',
            $dryRun,
            'categoriesCreated',
            $stats,
            ProductCategory::class,
            ['is_active' => true],
        );

        if (! $categoryWillExist) {
            // Unresolved + policy=skip.
            if (! $isUpdate) {
                return new ImportRowResult(
                    rowNumber: $rowNumber,
                    status: ImportRowResult::STATUS_ERROR,
                    sku: $sku,
                    errors: [sprintf('Catégorie "%s" inconnue (politique : ignorer) — obligatoire pour un nouveau produit.', $fields['category'])],
                    raw: $fields,
                );
            }

            $warnings[] = sprintf('Catégorie "%s" inconnue, conservée telle quelle (politique : ignorer).', $fields['category']);
        }

        // Sous-catégorie optionnelle : résolue/créée comme un enfant de la catégorie
        // ci-dessus (parent_id) ; si elle existe, le produit est rattaché à ELLE
        // (la feuille la plus précise), pas à la catégorie racine. Ignorée si le
        // champ est absent du CSV, ou si la catégorie racine elle-même n'a pas pu
        // être résolue (rien à quoi la rattacher).
        $subCategoryCreated = false;

        if ($fields['subcategory'] !== null && $categoryWillExist) {
            [$subCategoryId, $subCategoryWillExist, $subCategoryCreated] = $this->resolveReference(
                $fields['subcategory'],
                $categoriesBySlug,
                $policies['category'] ?? 'create',
                $dryRun,
                'categoriesCreated',
                $stats,
                ProductCategory::class,
                ['is_active' => true, 'parent_id' => $categoryId],
            );

            if ($subCategoryWillExist) {
                $categoryId = $subCategoryId;
            } else {
                $warnings[] = sprintf('Sous-catégorie "%s" inconnue, catégorie parente conservée (politique : ignorer).', $fields['subcategory']);
            }
        }

        [$brandId, $brandWillExist, $brandCreated] = $this->resolveReference(
            $fields['brand'],
            $brandsBySlug,
            $policies['brand'] ?? 'create',
            $dryRun,
            'brandsCreated',
            $stats,
            Brand::class,
            ['is_active' => true],
        );

        if (! $brandWillExist) {
            $warnings[] = sprintf('Marque "%s" inconnue, ignorée (politique : ignorer).', $fields['brand']);
        }

        $tagIds = [];
        $tagsToCreate = [];

        if ($fields['tags'] !== null) {
            foreach ($this->splitTags($fields['tags']) as $tagName) {
                [$tagId, $tagWillExist, $tagCreated] = $this->resolveReference(
                    $tagName,
                    $tagsBySlug,
                    $policies['tag'] ?? 'create',
                    $dryRun,
                    'tagsCreated',
                    $stats,
                    Tag::class,
                );

                if (! $tagWillExist) {
                    $warnings[] = sprintf('Tag "%s" inconnu, ignoré (politique : ignorer).', $tagName);

                    continue;
                }

                if ($tagCreated) {
                    $tagsToCreate[] = $tagName;
                }

                if ($tagId !== null) {
                    $tagIds[] = $tagId;
                }
            }
        }

        $stockQuantity = null;

        if ($fields['stock'] !== null) {
            $stockQuantity = $this->parseInt($fields['stock']);

            if ($stockQuantity === null || $stockQuantity < 0) {
                $warnings[] = 'Stock initial invalide, ignoré.';
                $stockQuantity = null;
            }
        }

        $stockImpact = null;

        if ($stockQuantity !== null) {
            if ($isUpdate) {
                $currentStock = $existingStockByProductId[$existingProduct->id] ?? null;
                $stockImpact = [
                    'current' => $currentStock?->quantity_available,
                    'incoming' => $stockQuantity,
                    'action' => 'replace',
                ];
            } else {
                $stockImpact = ['initial' => $stockQuantity];
            }
        }

        $costPrice = $this->parseOptionalDecimal($fields['cost_price'], $warnings, 'Prix d\'achat');
        $compareAtPrice = $this->parseOptionalDecimal($fields['compare_at_price'], $warnings, 'Prix barré');
        $taxRate = $this->parseOptionalDecimal($fields['tax_rate'], $warnings, 'TVA');
        $weight = $this->parseOptionalDecimal($fields['weight'], $warnings, 'Poids');
        $isActive = $this->parseOptionalBoolean($fields['is_active'], $warnings, 'Actif');
        $isFeatured = $this->parseOptionalBoolean($fields['is_featured'], $warnings, 'Mis en avant');
        $requiresPrescription = $this->parseOptionalBoolean($fields['requires_prescription'], $warnings, 'Ordonnance');

        // ProductService enforces compare_at_price >= price as a hard rule (throws
        // otherwise). Mirror that same fallback-to-existing-price logic here so the
        // preview never promises something the real import would then reject.
        $effectivePrice = $price ?? ($isUpdate ? (float) $existingProduct->price : null);

        if ($compareAtPrice !== null && $effectivePrice !== null && $compareAtPrice < $effectivePrice) {
            $warnings[] = 'Prix barré inférieur au prix de vente, ignoré.';
            $compareAtPrice = null;
        }

        $candidateValues = [
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => $fields['name'],
            'sku' => $sku,
            'barcode' => $fields['barcode'],
            'short_description' => $fields['short_description'],
            'description' => $fields['description'],
            'price' => $price,
            'cost_price' => $costPrice,
            'compare_at_price' => $compareAtPrice,
            'tax_rate' => $taxRate,
            'is_active' => $isActive,
            'is_featured' => $isFeatured,
            'requires_prescription' => $requiresPrescription,
            'weight' => $weight,
        ];

        $changedFields = [];
        $unchangedFields = [];

        if ($isUpdate) {
            foreach ($candidateValues as $field => $newValue) {
                if ($newValue === null || $field === 'sku') {
                    continue;
                }

                $currentValue = $existingProduct->{$field};

                $isSame = is_numeric($currentValue) && is_numeric($newValue)
                    ? (float) $currentValue === (float) $newValue
                    : $currentValue === $newValue;

                if ($isSame) {
                    $unchangedFields[] = $field;
                } else {
                    $changedFields[$field] = ['from' => $currentValue, 'to' => $newValue];
                }
            }
        }

        if (! $dryRun) {
            try {
                $this->persistRow(
                    $isUpdate,
                    $existingProduct,
                    $sku,
                    $candidateValues,
                    $tagIds,
                    (bool) ($fields['tags'] !== null),
                    $stockQuantity,
                    $store,
                );
            } catch (Throwable $exception) {
                return new ImportRowResult(
                    rowNumber: $rowNumber,
                    status: ImportRowResult::STATUS_ERROR,
                    sku: $sku,
                    errors: ['Échec de l\'enregistrement : '.$exception->getMessage()],
                    raw: $fields,
                );
            }
        }

        return new ImportRowResult(
            rowNumber: $rowNumber,
            status: $isUpdate ? ImportRowResult::STATUS_UPDATED : ImportRowResult::STATUS_NEW,
            sku: $sku,
            warnings: $warnings,
            changedFields: $changedFields,
            unchangedFields: $unchangedFields,
            categoryName: $fields['category'],
            categoryWillBeCreated: $categoryCreated,
            subCategoryName: $fields['subcategory'],
            subCategoryWillBeCreated: $subCategoryCreated,
            brandName: $fields['brand'],
            brandWillBeCreated: $brandCreated,
            tagsToCreate: $tagsToCreate,
            stockImpact: $stockImpact,
            raw: $fields,
        );
    }

    /**
     * @param  array<string, mixed>  $candidateValues
     */
    private function persistRow(
        bool $isUpdate,
        ?Product $existingProduct,
        string $sku,
        array $candidateValues,
        array $tagIds,
        bool $tagsColumnProvided,
        ?int $stockQuantity,
        Store $store,
    ): void {
        DB::transaction(function () use (
            $isUpdate,
            $existingProduct,
            $sku,
            $candidateValues,
            $tagIds,
            $tagsColumnProvided,
            $stockQuantity,
            $store,
        ) {
            if (! $isUpdate) {
                $payload = array_filter($candidateValues, fn ($value) => $value !== null);
                $payload['slug'] = Str::slug($candidateValues['name']);
                $payload['is_active'] = $candidateValues['is_active'] ?? true;
                $payload['is_featured'] = $candidateValues['is_featured'] ?? false;
                $payload['requires_prescription'] = $candidateValues['requires_prescription'] ?? false;
                $payload['sort_order'] = 0;

                $product = $this->productService->create($payload);
            } else {
                $payload = array_filter(
                    $candidateValues,
                    fn ($value, $key) => $value !== null && ! in_array($key, ['sku'], true),
                    ARRAY_FILTER_USE_BOTH
                );

                $product = $payload === []
                    ? $existingProduct
                    : $this->productService->update($existingProduct, $payload);
            }

            if ($tagsColumnProvided) {
                $this->productService->syncTags($product, $tagIds);
            }

            if ($stockQuantity !== null) {
                $this->productService->setInitialStock($product, $store, $stockQuantity);
            }
        });
    }

    /**
     * @param  array<int, string>  $record
     * @param  array<string, int|null>  $mapping
     * @return array<string, string|null>
     */
    private function extractFields(array $record, array $mapping): array
    {
        $fields = [];

        foreach (ProductColumnMapper::FIELD_ALIASES as $field => $aliases) {
            $index = $mapping[$field] ?? null;
            $value = $index !== null ? trim((string) ($record[$index] ?? '')) : '';
            $fields[$field] = $value === '' ? null : $value;
        }

        return $fields;
    }

    /**
     * Resolves a category/brand/tag by name against the shared, mutable
     * by-slug map. Returns [id, willExist, createdByThisRow]:
     * - willExist is false ONLY when the policy is "skip" and the name
     *   truly can't be found anywhere (not even pending from an earlier
     *   row in the same run) — that's the only case that should block a row.
     * - createdByThisRow is true only for the specific row that triggers
     *   the creation, so counts aren't inflated when several rows share
     *   a brand-new category/brand/tag.
     *
     * @return array{0: int|null, 1: bool, 2: bool}
     */
    private function resolveReference(
        ?string $name,
        array &$bySlug,
        string $policy,
        bool $dryRun,
        string $statKey,
        array &$stats,
        string $entityClass,
        array $extraAttributes = [],
    ): array {
        if ($name === null) {
            return [null, true, false];
        }

        $slug = Str::slug($name);
        $existing = $bySlug[$slug] ?? null;

        if ($existing !== null) {
            return [is_object($existing) ? $existing->id : null, true, false];
        }

        if ($policy === 'skip') {
            return [null, false, false];
        }

        $stats[$statKey]++;

        if ($dryRun) {
            $bySlug[$slug] = 'pending';

            return [null, true, true];
        }

        $model = $entityClass::create(array_merge(['name' => $name, 'slug' => $slug], $extraAttributes));
        $bySlug[$slug] = $model;

        return [$model->id, true, true];
    }

    /**
     * @return array<int, string>
     */
    private function splitTags(string $raw): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn (string $tag) => $tag !== ''
        ));
    }

    private function parseDecimal(string $raw): ?float
    {
        $value = trim($raw);
        $value = preg_replace('/[^0-9,.\-]/', '', $value) ?? '';

        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',') && ! str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function parseInt(string $raw): ?int
    {
        $decimal = $this->parseDecimal($raw);

        return $decimal === null ? null : (int) round($decimal);
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function parseOptionalDecimal(?string $raw, array &$warnings, string $label): ?float
    {
        if ($raw === null) {
            return null;
        }

        $value = $this->parseDecimal($raw);

        if ($value === null || $value < 0) {
            $warnings[] = sprintf('%s invalide, ignoré.', $label);

            return null;
        }

        return $value;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function parseOptionalBoolean(?string $raw, array &$warnings, string $label): ?bool
    {
        if ($raw === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($raw));

        $value = match ($normalized) {
            '1', 'oui', 'yes', 'true', 'vrai' => true,
            '0', 'non', 'no', 'false', 'faux' => false,
            default => null,
        };

        if ($value === null) {
            $warnings[] = sprintf('%s : valeur "%s" non reconnue, ignorée.', $label, $raw);
        }

        return $value;
    }
}
