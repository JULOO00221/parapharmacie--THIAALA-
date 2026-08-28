<?php

namespace App\Services\Imports\Dto;

class ImportRowResult
{
    public const STATUS_NEW = 'new';

    public const STATUS_UPDATED = 'updated';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_ERROR = 'error';

    /**
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     * @param  array<string, array{from: mixed, to: mixed}>  $changedFields
     * @param  array<int, string>  $unchangedFields fields present in the CSV but identical to the current value
     * @param  array<int, string>  $tagsToCreate
     * @param  array<string, mixed>|null  $stockImpact
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly int $rowNumber,
        public readonly string $status,
        public readonly ?string $sku = null,
        public readonly array $errors = [],
        public readonly array $warnings = [],
        public readonly array $changedFields = [],
        public readonly array $unchangedFields = [],
        public readonly ?string $categoryName = null,
        public readonly bool $categoryWillBeCreated = false,
        public readonly ?string $subCategoryName = null,
        public readonly bool $subCategoryWillBeCreated = false,
        public readonly ?string $brandName = null,
        public readonly bool $brandWillBeCreated = false,
        public readonly array $tagsToCreate = [],
        public readonly ?array $stockImpact = null,
        public readonly array $raw = [],
    ) {
    }

    public function isPersisted(): bool
    {
        return in_array($this->status, [self::STATUS_NEW, self::STATUS_UPDATED], true);
    }
}
