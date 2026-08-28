<x-filament-panels::page>
    <div class="flex items-center gap-4 mb-6 text-sm">
        @foreach (['1' => 'Upload', '2' => 'Mapping', '3' => 'Prévisualisation', '4' => 'Rapport'] as $stepNumber => $label)
            <div @class([
                'px-3 py-1 rounded-full font-medium',
                'bg-primary-600 text-white' => $step == $stepNumber,
                'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => $step != $stepNumber,
            ])>
                {{ $stepNumber }}. {{ $label }}
            </div>
        @endforeach
    </div>

    @if ($step === 1)
        <form wire:submit.prevent="analyzeFile" class="space-y-6">
            {{ $this->form }}

            <x-filament::button type="submit">
                Analyser le fichier
            </x-filament::button>
        </form>
    @endif

    @if ($step === 2)
        <div class="space-y-6">
            <div class="rounded-lg border p-4 dark:border-gray-700">
                <p><strong>Lignes détectées :</strong> {{ $analysis['row_count'] ?? 0 }}</p>
                <p><strong>Colonnes détectées :</strong> {{ implode(', ', $analysis['headers'] ?? []) }}</p>
                @if (! empty($analysis['missing_required']))
                    <p class="text-danger-600">
                        <strong>Colonnes obligatoires non détectées automatiquement :</strong>
                        {{ implode(', ', $analysis['missing_required']) }} — merci de les mapper manuellement ci-dessous si une colonne équivalente existe.
                    </p>
                @endif
            </div>

            <form wire:submit.prevent="generatePreview" class="space-y-6">
                {{ $this->form }}

                <div class="flex gap-3">
                    <x-filament::button color="gray" wire:click="backToUpload" type="button">
                        Retour
                    </x-filament::button>
                    <x-filament::button type="submit">
                        Prévisualiser
                    </x-filament::button>
                </div>
            </form>
        </div>
    @endif

    @if ($step === 3)
        <div class="space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="rounded-lg border p-4 dark:border-gray-700">
                    <p class="text-2xl font-bold">{{ $previewSummary['total_rows'] ?? 0 }}</p>
                    <p class="text-sm text-gray-500">Total lignes</p>
                </div>
                <div class="rounded-lg border p-4 dark:border-gray-700">
                    <p class="text-2xl font-bold text-success-600">{{ $previewSummary['created'] ?? 0 }}</p>
                    <p class="text-sm text-gray-500">Nouveaux produits</p>
                </div>
                <div class="rounded-lg border p-4 dark:border-gray-700">
                    <p class="text-2xl font-bold text-warning-600">{{ $previewSummary['updated'] ?? 0 }}</p>
                    <p class="text-sm text-gray-500">Produits mis à jour</p>
                </div>
                <div class="rounded-lg border p-4 dark:border-gray-700">
                    <p class="text-2xl font-bold text-danger-600">{{ $previewSummary['errors'] ?? 0 }}</p>
                    <p class="text-sm text-gray-500">Erreurs</p>
                </div>
                <div class="rounded-lg border p-4 dark:border-gray-700">
                    <p class="text-2xl font-bold">{{ $previewSummary['duplicates'] ?? 0 }}</p>
                    <p class="text-sm text-gray-500">Doublons internes</p>
                </div>
                <div class="rounded-lg border p-4 dark:border-gray-700">
                    <p class="text-2xl font-bold">{{ $previewSummary['categories_created'] ?? 0 }}</p>
                    <p class="text-sm text-gray-500">Catégories à créer</p>
                </div>
                <div class="rounded-lg border p-4 dark:border-gray-700">
                    <p class="text-2xl font-bold">{{ $previewSummary['brands_created'] ?? 0 }}</p>
                    <p class="text-sm text-gray-500">Marques à créer</p>
                </div>
                <div class="rounded-lg border p-4 dark:border-gray-700">
                    <p class="text-2xl font-bold">{{ $previewSummary['tags_created'] ?? 0 }}</p>
                    <p class="text-sm text-gray-500">Tags à créer</p>
                </div>
            </div>

            <p class="text-sm text-gray-500">Aperçu des {{ count($previewRows) }} premières lignes (calculé sans aucune écriture en base) :</p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="text-left border-b dark:border-gray-700">
                            <th class="p-2">Ligne</th>
                            <th class="p-2">SKU</th>
                            <th class="p-2">Statut</th>
                            <th class="p-2">Champs modifiés</th>
                            <th class="p-2">Stock</th>
                            <th class="p-2">Messages</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($previewRows as $row)
                            <tr class="border-b dark:border-gray-800 align-top">
                                <td class="p-2">{{ $row['row_number'] }}</td>
                                <td class="p-2">{{ $row['sku'] ?? '—' }}</td>
                                <td class="p-2">
                                    <span @class([
                                        'px-2 py-0.5 rounded text-xs font-medium',
                                        'bg-success-100 text-success-700' => $row['status'] === 'new',
                                        'bg-warning-100 text-warning-700' => $row['status'] === 'updated',
                                        'bg-gray-100 text-gray-600' => $row['status'] === 'duplicate',
                                        'bg-danger-100 text-danger-700' => $row['status'] === 'error',
                                        'bg-gray-200 text-gray-700' => $row['status'] === 'skipped',
                                    ])>
                                        {{ match ($row['status']) {
                                            'new' => 'Nouveau',
                                            'updated' => 'Mis à jour',
                                            'duplicate' => 'Doublon',
                                            'error' => 'Erreur',
                                            'skipped' => 'Ignoré',
                                            default => $row['status'],
                                        } }}
                                    </span>
                                    @if ($row['category_will_be_created'])
                                        <div class="text-xs text-gray-500">Catégorie "{{ $row['category_name'] }}" à créer</div>
                                    @endif
                                    @if ($row['sub_category_will_be_created'])
                                        <div class="text-xs text-gray-500">Sous-catégorie "{{ $row['sub_category_name'] }}" à créer</div>
                                    @endif
                                    @if ($row['brand_will_be_created'])
                                        <div class="text-xs text-gray-500">Marque "{{ $row['brand_name'] }}" à créer</div>
                                    @endif
                                    @if (! empty($row['tags_to_create']))
                                        <div class="text-xs text-gray-500">Tags à créer : {{ implode(', ', $row['tags_to_create']) }}</div>
                                    @endif
                                </td>
                                <td class="p-2">
                                    @forelse ($row['changed_fields'] as $field => $change)
                                        <div>{{ $field }} : {{ $change['from'] }} → {{ $change['to'] }}</div>
                                    @empty
                                        @if (! empty($row['unchanged_fields']))
                                            <span class="text-xs text-gray-400">inchangé</span>
                                        @endif
                                    @endforelse
                                </td>
                                <td class="p-2">
                                    @if ($row['stock_impact'])
                                        @if (isset($row['stock_impact']['initial']))
                                            Stock initial : {{ $row['stock_impact']['initial'] }}
                                        @else
                                            {{ $row['stock_impact']['current'] ?? '—' }} → {{ $row['stock_impact']['incoming'] }} (remplacement)
                                        @endif
                                    @endif
                                </td>
                                <td class="p-2">
                                    @foreach ($row['errors'] as $error)
                                        <div class="text-danger-600 text-xs">{{ $error }}</div>
                                    @endforeach
                                    @foreach ($row['warnings'] as $warning)
                                        <div class="text-warning-600 text-xs">{{ $warning }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex gap-3">
                <x-filament::button color="gray" wire:click="backToMapping">
                    Retour
                </x-filament::button>
                {{ $this->runImportAction }}
            </div>
        </div>
    @endif

    @if ($step === 4 && $finalReport)
        <div class="space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="rounded-lg border p-4 dark:border-gray-700">
                    <p class="text-2xl font-bold">{{ $finalReport['total_rows'] }}</p>
                    <p class="text-sm text-gray-500">Total lignes</p>
                </div>
                <div class="rounded-lg border p-4 dark:border-gray-700">
                    <p class="text-2xl font-bold text-success-600">{{ $finalReport['created'] }}</p>
                    <p class="text-sm text-gray-500">Nouveaux produits</p>
                </div>
                <div class="rounded-lg border p-4 dark:border-gray-700">
                    <p class="text-2xl font-bold text-warning-600">{{ $finalReport['updated'] }}</p>
                    <p class="text-sm text-gray-500">Produits mis à jour</p>
                </div>
                <div class="rounded-lg border p-4 dark:border-gray-700">
                    <p class="text-2xl font-bold text-danger-600">{{ $finalReport['errors'] }}</p>
                    <p class="text-sm text-gray-500">Erreurs</p>
                </div>
                <div class="rounded-lg border p-4 dark:border-gray-700">
                    <p class="text-2xl font-bold">{{ $finalReport['duplicates'] }}</p>
                    <p class="text-sm text-gray-500">Doublons internes</p>
                </div>
                <div class="rounded-lg border p-4 dark:border-gray-700">
                    <p class="text-2xl font-bold">{{ $finalReport['categories_created'] }}</p>
                    <p class="text-sm text-gray-500">Catégories créées</p>
                </div>
                <div class="rounded-lg border p-4 dark:border-gray-700">
                    <p class="text-2xl font-bold">{{ $finalReport['brands_created'] }}</p>
                    <p class="text-sm text-gray-500">Marques créées</p>
                </div>
                <div class="rounded-lg border p-4 dark:border-gray-700">
                    <p class="text-2xl font-bold">{{ $finalReport['tags_created'] }}</p>
                    <p class="text-sm text-gray-500">Tags créés</p>
                </div>
            </div>

            @if ($finalReport['errors'] > 0)
                <x-filament::button color="danger" wire:click="downloadErrorReport">
                    Télécharger le rapport d'erreurs (CSV)
                </x-filament::button>
            @endif

            <x-filament::button color="gray" wire:click="startNewImport">
                Importer un autre fichier
            </x-filament::button>
        </div>
    @endif
</x-filament-panels::page>
