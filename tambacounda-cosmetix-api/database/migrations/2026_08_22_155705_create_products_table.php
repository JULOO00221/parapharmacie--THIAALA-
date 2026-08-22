<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('product_categories')
                ->restrictOnDelete();
            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();

            // Montants exprimés en FCFA (pas de décimales fractionnaires de devise à gérer).
            $table->decimal('price', 12, 2);
            $table->decimal('cost_price', 12, 2)->nullable();
            // Prix de référence/prix barré : doit être >= price. Règle appliquée
            // au niveau applicatif (ProductService) et couverte par les tests,
            // car une contrainte CHECK inter-colonnes ajouterait une complexité
            // disproportionnée pour ce que la Phase 2 requiert.
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('requires_prescription')->default(false);
            $table->decimal('weight', 8, 3)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id');
            $table->index('brand_id');
            $table->index('is_active');
            $table->index('is_featured');
        });

        // Contraintes de cohérence numérique (PostgreSQL). Une valeur NULL
        // satisfait toujours un CHECK, donc les colonnes nullable restent
        // optionnelles sans clause supplémentaire.
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_non_negative CHECK (price >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_cost_price_non_negative CHECK (cost_price >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_compare_at_price_non_negative CHECK (compare_at_price >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_tax_rate_non_negative CHECK (tax_rate >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_weight_non_negative CHECK (weight >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
