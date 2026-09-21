<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Photos trouvées sur une source externe (Open Beauty Facts) et mises en
 * attente de validation humaine. Rien n'est jamais rattaché directement à un
 * produit : une ligne ici est une proposition, jamais une image publiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_image_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            // Source et traçabilité : on doit pouvoir remonter à l'origine
            // exacte d'une photo, y compris après validation.
            $table->string('source')->default('open_beauty_facts');
            $table->string('source_code')->nullable();      // code produit chez la source
            $table->string('source_image_url', 1024);
            $table->string('source_page_url', 1024)->nullable();
            $table->string('source_product_name')->nullable();
            $table->string('source_brand')->nullable();
            $table->string('source_quantity')->nullable();

            // Licence : obligatoire pour pouvoir afficher l'attribution exigée.
            $table->string('license_code');                 // ex. CC-BY-SA-3.0
            $table->string('license_url', 512);
            $table->string('attribution', 512);             // texte à afficher tel quel

            // Correspondance.
            $table->string('match_method');                 // barcode | brand_name
            $table->string('search_query')->nullable();     // requête qui a produit ce résultat
            $table->unsignedSmallInteger('confidence');     // 0-100
            /** @var array<string, mixed> détail du score, pour expliquer la note au validateur */
            $table->json('confidence_breakdown')->nullable();

            // Fichier déjà redimensionné/converti, prêt à être publié tel quel.
            // NULL en --dry-run : la proposition existe, le fichier n'a pas été téléchargé.
            $table->string('path')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('bytes')->nullable();

            $table->string('status')->default('pending');   // pending | approved | rejected
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 512)->nullable();

            // Image effectivement créée lors de la validation : rend
            // l'approbation idempotente (rejouer ne duplique pas la photo).
            $table->foreignId('product_image_id')
                ->nullable()
                ->constrained('product_images')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('product_id');
            $table->index('status');
            $table->index(['status', 'confidence']);
        });

        // Une même photo distante ne peut être proposée qu'une fois par produit :
        // relancer la commande met à jour la proposition au lieu d'en empiler.
        DB::statement(
            'CREATE UNIQUE INDEX product_image_candidates_unique_source
             ON product_image_candidates (product_id, md5(source_image_url))'
        );

        DB::statement(
            'ALTER TABLE product_image_candidates
             ADD CONSTRAINT product_image_candidates_confidence_range
             CHECK (confidence >= 0 AND confidence <= 100)'
        );

        DB::statement(
            "ALTER TABLE product_image_candidates
             ADD CONSTRAINT product_image_candidates_status_values
             CHECK (status IN ('pending', 'approved', 'rejected'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('product_image_candidates');
    }
};
