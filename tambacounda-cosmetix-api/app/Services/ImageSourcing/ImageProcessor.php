<?php

namespace App\Services\ImageSourcing;

use App\Services\ImageSourcing\Dto\ProcessedImage;
use GdImage;

/**
 * Réduit une photo distante au format attendu par la boutique : 800 px de
 * large, WebP, moins de 80 Ko.
 *
 * Le budget de poids prime sur la qualité : la boutique est consultée depuis
 * Tambacounda, souvent en 3G, et une fiche produit qui charge une photo de
 * 400 Ko ne s'affiche pas. On dégrade donc la qualité par paliers jusqu'à
 * passer sous le seuil, et on renonce à l'image plutôt que de publier un
 * fichier hors budget.
 *
 * GD est utilisé directement : l'extension est déjà présente et sait écrire
 * du WebP, ce qui évite d'ajouter une dépendance de traitement d'image.
 */
class ImageProcessor
{
    public function process(string $binary): ?ProcessedImage
    {
        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            return null;
        }

        if (! $this->looksLikeAProductPhoto($source)) {
            imagedestroy($source);

            return null;
        }

        $resized = $this->resize($source, (int) config('image_sourcing.image.width'));
        imagedestroy($source);

        if ($resized === null) {
            return null;
        }

        $result = $this->encodeWithinBudget($resized);
        imagedestroy($resized);

        return $result;
    }

    /**
     * Écarte ce qui n'est visiblement pas une photo de produit.
     *
     * Les contributeurs photographient parfois la tranche d'une boîte ou un
     * bandeau d'étiquette : l'image est alors très allongée. Placée dans une
     * vignette carrée, elle ne montre rien d'utilisable. Une photo de produit
     * reste dans des proportions raisonnables, quelle que soit la forme du
     * flacon.
     */
    private function looksLikeAProductPhoto(GdImage $image): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width < 200 || $height < 200) {
            return false;
        }

        $ratio = max($width / $height, $height / $width);

        return $ratio <= (float) config('image_sourcing.image.max_aspect_ratio');
    }

    /**
     * Ramène l'image à la largeur cible en conservant ses proportions. Une
     * image déjà plus étroite est laissée telle quelle : l'agrandir ne
     * créerait pas de détail, seulement du poids.
     */
    private function resize(GdImage $source, int $targetWidth): ?GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);

        if ($width <= 0 || $height <= 0) {
            return null;
        }

        if ($width <= $targetWidth) {
            $targetWidth = $width;
            $targetHeight = $height;
        } else {
            $targetHeight = max(1, (int) round($height * ($targetWidth / $width)));
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($canvas === false) {
            return null;
        }

        // Les photos de produit sont souvent des PNG détourés sur fond
        // transparent ; sans fond blanc explicite, la transparence vire au
        // noir à la conversion.
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $canvas;
    }

    private function encodeWithinBudget(GdImage $image): ?ProcessedImage
    {
        $maxBytes = (int) config('image_sourcing.image.max_bytes');
        /** @var list<int> $steps */
        $steps = config('image_sourcing.image.quality_steps');
        $last = null;

        foreach ($steps as $quality) {
            ob_start();
            imagewebp($image, null, $quality);
            $encoded = (string) ob_get_clean();

            $last = new ProcessedImage(
                binary: $encoded,
                width: imagesx($image),
                height: imagesy($image),
                bytes: strlen($encoded),
                quality: $quality,
            );

            if ($last->bytes <= $maxBytes) {
                return $last;
            }
        }

        // Même à la qualité la plus basse le budget n'est pas tenu : on rend
        // le résultat en le marquant, l'appelant décidera d'abandonner.
        return $last?->markOverBudget();
    }
}
