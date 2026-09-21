<?php

namespace App\Services\ImageSourcing;

use App\Models\ProductImage;
use App\Models\ProductImageCandidate;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Applique la décision humaine sur une photo proposée.
 *
 * C'est le seul endroit du code où une photo trouvée automatiquement devient
 * une photo publiée. L'opération est transactionnelle et idempotente, comme
 * l'exige le projet pour toute écriture métier : valider deux fois la même
 * proposition ne crée pas deux images, et une erreur en cours de route ne
 * laisse ni image orpheline ni proposition à moitié traitée.
 */
class ImageCandidateReviewer
{
    public function __construct(private readonly StorageService $storage) {}

    public function approve(ProductImageCandidate $candidate, User $reviewer): ProductImage
    {
        // Rejouer une validation déjà appliquée rend simplement l'image créée.
        if ($candidate->status === ProductImageCandidate::STATUS_APPROVED && $candidate->productImage !== null) {
            return $candidate->productImage;
        }

        if (! $candidate->isPending()) {
            throw new RuntimeException('Cette proposition a déjà été traitée.');
        }

        if (! $candidate->hasDownloadedFile()) {
            throw new RuntimeException(
                "La photo n'a pas été téléchargée (proposition issue d'une simulation). "
                .'Relancez products:source-images sans --dry-run.'
            );
        }

        $disk = $this->storage->filesystem();

        if (! $disk->exists((string) $candidate->path)) {
            throw new RuntimeException('Le fichier de la proposition est introuvable sur le stockage.');
        }

        // La copie du fichier a lieu avant la transaction : une écriture sur
        // disque ne se défait pas par un rollback. Si la transaction échoue,
        // la copie reste orpheline — sans conséquence, contrairement à une
        // ligne en base pointant vers un fichier absent.
        $target = sprintf(
            '%s/%s-%s.webp',
            trim((string) config('image_sourcing.image.approved_directory'), '/'),
            $candidate->product_id,
            Str::random(12),
        );

        $disk->put($target, (string) $disk->get((string) $candidate->path));

        return DB::transaction(function () use ($candidate, $reviewer, $target): ProductImage {
            $product = $candidate->product()->lockForUpdate()->firstOrFail();

            $hasPrimary = ProductImage::query()
                ->where('product_id', $product->id)
                ->where('is_primary', true)
                ->exists();

            // La photo se place après les existantes ; la première prend 0,
            // comme toute image créée par le back-office.
            $lastPosition = ProductImage::query()->where('product_id', $product->id)->max('sort_order');

            $image = ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => $target,
                'alt_text' => $product->name,
                'sort_order' => $lastPosition === null ? 0 : (int) $lastPosition + 1,
                'is_primary' => ! $hasPrimary,
                'source' => $candidate->source,
                'source_url' => $candidate->source_page_url,
                'license_code' => $candidate->license_code,
                'license_url' => $candidate->license_url,
                'attribution' => $candidate->attribution,
            ]);

            $candidate->forceFill([
                'status' => ProductImageCandidate::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'product_image_id' => $image->id,
            ])->save();

            // Les autres propositions encore en attente pour ce produit n'ont
            // plus lieu d'être : la fiche a sa photo.
            ProductImageCandidate::query()
                ->where('product_id', $product->id)
                ->whereKeyNot($candidate->id)
                ->pending()
                ->update([
                    'status' => ProductImageCandidate::STATUS_REJECTED,
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now(),
                    'review_note' => 'Une autre photo a été validée pour ce produit.',
                ]);

            return $image;
        });
    }

    public function reject(ProductImageCandidate $candidate, User $reviewer, ?string $note = null): void
    {
        if (! $candidate->isPending()) {
            return;
        }

        $file = $candidate->path;

        DB::transaction(function () use ($candidate, $reviewer, $note): void {
            $candidate->forceFill([
                'status' => ProductImageCandidate::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
                // Le fichier va être supprimé : la ligne ne doit plus le désigner.
                'path' => null,
            ])->save();
        });

        // Le fichier téléchargé n'a plus d'usage. L'URL d'origine, elle, reste
        // en base : c'est elle qui empêchera la commande de reproposer la même
        // photo à la prochaine exécution.
        if ($file !== null) {
            $this->storage->delete($file);
        }
    }
}
