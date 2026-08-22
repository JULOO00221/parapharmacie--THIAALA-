<?php

namespace App\Services\Imports\Dto;

class ImportReport
{
    /**
     * @param  array<int, ImportRowResult>  $rows
     */
    public function __construct(
        public readonly int $totalRows,
        public readonly int $created,
        public readonly int $updated,
        public readonly int $duplicates,
        public readonly int $skipped,
        public readonly int $errors,
        public readonly int $categoriesCreated,
        public readonly int $brandsCreated,
        public readonly int $tagsCreated,
        public readonly array $rows,
    ) {
    }

    /**
     * @return array<int, ImportRowResult>
     */
    public function erroredRows(): array
    {
        return array_values(array_filter(
            $this->rows,
            fn (ImportRowResult $row) => $row->status === ImportRowResult::STATUS_ERROR
        ));
    }

    public function toErrorCsv(): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, ['Ligne', 'SKU', 'Erreurs']);

        foreach ($this->erroredRows() as $row) {
            fputcsv($handle, [
                $row->rowNumber,
                $row->sku ?? '',
                implode(' | ', $row->errors),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }
}
