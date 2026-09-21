<?php

namespace App\Services\ImageSourcing\Dto;

/** Une photo redimensionnée et convertie, prête à être stockée. */
final readonly class ProcessedImage
{
    public function __construct(
        public string $binary,
        public int $width,
        public int $height,
        public int $bytes,
        public int $quality,
        public bool $overBudget = false,
    ) {}

    /** Marque une image qui n'a pas pu tenir dans le budget de poids. */
    public function markOverBudget(): self
    {
        return new self($this->binary, $this->width, $this->height, $this->bytes, $this->quality, overBudget: true);
    }

    public function humanSize(): string
    {
        return number_format($this->bytes / 1024, 1, ',', ' ').' Ko';
    }
}
