<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            // Clé/chemin de stockage relatif au bucket (jamais une URL en dur).
            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('product_id');
        });

        // Garantit au niveau base de données qu'un seul is_primary = true
        // peut exister par produit (index unique partiel PostgreSQL).
        DB::statement(
            'CREATE UNIQUE INDEX product_images_one_primary_per_product ON product_images (product_id) WHERE is_primary = true'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
