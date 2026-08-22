<?php

namespace App\Filament\Pages;

use App\Models\Store;
use App\Services\Imports\Dto\ImportReport;
use App\Services\Imports\Dto\ImportRowResult;
use App\Services\Imports\ProductColumnMapper;
use App\Services\Imports\ProductCsvImportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Custom Filament page orchestrating the CSV product import wizard. All
 * business logic (parsing, mapping, validation, transactional import)
 * lives in ProductCsvImportService — this page only wires the UI steps
 * to it and shapes results into plain arrays for the Blade view.
 *
 * @property-read Schema $form
 */
class ImportProducts extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Import produits CSV';

    protected static ?string $title = 'Import produits CSV';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.import-products';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public int $step = 1;

    public ?int $storeId = null;

    public string $categoryPolicy = 'create';

    public string $brandPolicy = 'create';

    public string $tagPolicy = 'create';

    public ?string $csvAbsolutePath = null;

    /** @var array<string, mixed> */
    public array $analysis = [];

    /** @var array<int, array<string, mixed>> */
    public array $previewRows = [];

    /** @var array<string, mixed> */
    public array $previewSummary = [];

    /** @var array<string, mixed>|null */
    public ?array $finalReport = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('csv_file')
                    ->label('Fichier CSV')
                    ->disk('local')
                    ->directory('imports')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/csv'])
                    ->maxSize(5120)
                    ->required()
                    ->visible(fn () => $this->step === 1),
                Select::make('store_id')
                    ->label('Boutique')
                    ->options(fn () => Store::query()->where('is_active', true)->pluck('name', 'id'))
                    ->required()
                    ->visible(fn () => $this->step === 1),
                Select::make('category_policy')
                    ->label('Catégorie inconnue')
                    ->options(['create' => 'Créer automatiquement', 'skip' => 'Ignorer la ligne'])
                    ->default('create')
                    ->required()
                    ->visible(fn () => $this->step === 1),
                Select::make('brand_policy')
                    ->label('Marque inconnue')
                    ->options(['create' => 'Créer automatiquement', 'skip' => 'Ignorer la ligne'])
                    ->default('create')
                    ->required()
                    ->visible(fn () => $this->step === 1),
                Select::make('tag_policy')
                    ->label('Tag inconnu')
                    ->options(['create' => 'Créer automatiquement', 'skip' => 'Ignorer la ligne'])
                    ->default('create')
                    ->required()
                    ->visible(fn () => $this->step === 1),
                ...$this->mappingFields(),
            ])
            ->statePath('data');
    }

    /**
     * Always defines these components (rather than only when analysis is
     * present) so the schema's structure is stable across requests — only
     * their options/visibility are re-evaluated lazily via closures, which
     * Filament does re-run on every render.
     *
     * @return array<int, Select>
     */
    private function mappingFields(): array
    {
        return collect(array_keys(ProductColumnMapper::FIELD_ALIASES))
            ->map(fn (string $field) => Select::make("mapping.{$field}")
                ->label($this->fieldLabel($field))
                ->options(fn () => collect($this->analysis['headers'] ?? [])
                    ->mapWithKeys(fn (string $header, int $index) => [$index => $header.' (colonne '.($index + 1).')'])
                    ->all())
                ->native(false)
                ->visible(fn () => $this->step === 2))
            ->all();
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'name' => 'Nom',
            'sku' => 'SKU',
            'barcode' => 'Code-barres',
            'price' => 'Prix',
            'cost_price' => 'Prix d\'achat',
            'compare_at_price' => 'Prix barré',
            'tax_rate' => 'TVA',
            'category' => 'Catégorie',
            'brand' => 'Marque',
            'short_description' => 'Description courte',
            'description' => 'Description',
            'is_active' => 'Actif',
            'is_featured' => 'Mis en avant',
            'requires_prescription' => 'Ordonnance',
            'weight' => 'Poids',
            'tags' => 'Tags',
            'stock' => 'Stock initial',
            default => $field,
        };
    }

    public function analyzeFile(): void
    {
        $state = $this->form->getState();

        $absolutePath = Storage::disk('local')->path($state['csv_file']);

        try {
            $this->analysis = app(ProductCsvImportService::class)->analyze($absolutePath);
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Fichier CSV invalide')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->csvAbsolutePath = $absolutePath;
        $this->storeId = (int) $state['store_id'];
        $this->categoryPolicy = $state['category_policy'];
        $this->brandPolicy = $state['brand_policy'];
        $this->tagPolicy = $state['tag_policy'];

        $this->data['mapping'] = $this->analysis['mapping'];
        $this->step = 2;
    }

    public function generatePreview(): void
    {
        $state = $this->form->getState();
        $mapping = $state['mapping'] ?? [];

        $missing = ProductColumnMapper::missingRequiredFields($mapping);

        if ($missing !== []) {
            Notification::make()
                ->title('Mapping incomplet')
                ->body('Colonnes obligatoires non associées : '.implode(', ', $missing))
                ->danger()
                ->send();

            return;
        }

        $result = app(ProductCsvImportService::class)->preview(
            $this->csvAbsolutePath,
            $mapping,
            Store::findOrFail($this->storeId),
            $this->policies(),
            limit: 50,
        );

        $this->previewRows = array_map(fn (ImportRowResult $row) => $this->rowToArray($row), $result['preview_rows']);
        $this->previewSummary = $this->reportSummary($result['report']);
        $this->step = 3;
    }

    public function backToUpload(): void
    {
        $this->step = 1;
    }

    public function backToMapping(): void
    {
        $this->step = 2;
    }

    public function runImportAction(): Action
    {
        return Action::make('runImport')
            ->label('Confirmer l\'import')
            ->color('success')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->requiresConfirmation()
            ->modalHeading('Confirmer l\'import des produits')
            ->modalDescription('Cette action va créer et mettre à jour des produits dans la base de données. Cette opération ne peut pas être annulée automatiquement.')
            ->modalSubmitActionLabel('Lancer l\'import')
            ->action(function () {
                $mapping = $this->data['mapping'] ?? [];

                $report = app(ProductCsvImportService::class)->import(
                    $this->csvAbsolutePath,
                    $mapping,
                    Store::findOrFail($this->storeId),
                    $this->policies(),
                );

                $this->finalReport = $this->reportSummary($report);
                $this->finalReport['rows'] = array_map(fn (ImportRowResult $row) => $this->rowToArray($row), $report->rows);
                $this->finalReport['error_csv'] = $report->toErrorCsv();
                $this->step = 4;

                Notification::make()
                    ->title('Import terminé')
                    ->body(sprintf(
                        '%d créés, %d mis à jour, %d erreurs, %d doublons.',
                        $report->created,
                        $report->updated,
                        $report->errors,
                        $report->duplicates,
                    ))
                    ->success()
                    ->send();
            });
    }

    public function downloadErrorReport()
    {
        $csv = $this->finalReport['error_csv'] ?? '';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'rapport-erreurs-import.csv');
    }

    public function startNewImport(): void
    {
        $this->step = 1;
        $this->storeId = null;
        $this->csvAbsolutePath = null;
        $this->analysis = [];
        $this->previewRows = [];
        $this->previewSummary = [];
        $this->finalReport = null;
        $this->data = [];
        $this->form->fill();
    }

    /**
     * @return array{category: string, brand: string, tag: string}
     */
    private function policies(): array
    {
        return [
            'category' => $this->categoryPolicy,
            'brand' => $this->brandPolicy,
            'tag' => $this->tagPolicy,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rowToArray(ImportRowResult $row): array
    {
        return [
            'row_number' => $row->rowNumber,
            'status' => $row->status,
            'sku' => $row->sku,
            'errors' => $row->errors,
            'warnings' => $row->warnings,
            'changed_fields' => $row->changedFields,
            'unchanged_fields' => $row->unchangedFields,
            'category_name' => $row->categoryName,
            'category_will_be_created' => $row->categoryWillBeCreated,
            'brand_name' => $row->brandName,
            'brand_will_be_created' => $row->brandWillBeCreated,
            'tags_to_create' => $row->tagsToCreate,
            'stock_impact' => $row->stockImpact,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportSummary(ImportReport $report): array
    {
        return [
            'total_rows' => $report->totalRows,
            'created' => $report->created,
            'updated' => $report->updated,
            'duplicates' => $report->duplicates,
            'skipped' => $report->skipped,
            'errors' => $report->errors,
            'categories_created' => $report->categoriesCreated,
            'brands_created' => $report->brandsCreated,
            'tags_created' => $report->tagsCreated,
        ];
    }
}
