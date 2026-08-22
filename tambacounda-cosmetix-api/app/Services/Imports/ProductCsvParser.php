<?php

namespace App\Services\Imports;

use RuntimeException;

/**
 * Reads an uploaded CSV file into headers + raw string rows. Handles the
 * UTF-8 BOM some spreadsheet software adds, and auto-detects whether the
 * file uses ";" or "," as a field separator (common source of confusion
 * for French-locale exports). Uses fgetcsv() (not a manual line-split) so
 * quoted fields containing embedded newlines are parsed correctly.
 */
class ProductCsvParser
{
    private const MAX_ROWS = 20000;

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>, delimiter: string}
     */
    public function parse(string $absolutePath): array
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException('Le fichier CSV est introuvable ou illisible.');
        }

        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Impossible de lire le fichier CSV.');
        }

        $firstLine = fgets($handle);
        $delimiter = $this->detectDelimiter((string) $firstLine);
        rewind($handle);

        $headers = null;
        $rows = [];

        try {
            while (($record = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                if ($record === [null] || $record === false) {
                    continue;
                }

                $record = array_map(function ($value) {
                    $value = (string) $value;

                    if (! mb_check_encoding($value, 'UTF-8')) {
                        $value = @mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1') ?: $value;
                    }

                    // Strip a UTF-8 BOM only ever found on the very first cell.
                    return str_starts_with($value, "\xEF\xBB\xBF") ? substr($value, 3) : $value;
                }, $record);

                if ($headers === null) {
                    $headers = array_map(fn ($value) => trim($value), $record);

                    continue;
                }

                if (count(array_filter($record, fn ($value) => trim($value) !== '')) === 0) {
                    continue;
                }

                $rows[] = $record;

                if (count($rows) > self::MAX_ROWS) {
                    throw new RuntimeException(sprintf(
                        'Le fichier dépasse la limite de %d lignes prise en charge.',
                        self::MAX_ROWS
                    ));
                }
            }
        } finally {
            fclose($handle);
        }

        if ($headers === null) {
            throw new RuntimeException('Le fichier CSV est vide.');
        }

        if (count(array_filter($headers, fn (string $header): bool => $header !== '')) === 0) {
            throw new RuntimeException('Aucune colonne détectée dans l\'en-tête du CSV.');
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'delimiter' => $delimiter,
        ];
    }

    private function detectDelimiter(string $headerLine): string
    {
        $semicolons = substr_count($headerLine, ';');
        $commas = substr_count($headerLine, ',');

        return $semicolons > $commas ? ';' : ',';
    }
}
