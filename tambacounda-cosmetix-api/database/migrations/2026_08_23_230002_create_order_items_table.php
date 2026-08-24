<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();
            // Nullable + nullOnDelete (pas restrict) : un produit doit
            // pouvoir être supprimé sans jamais bloquer sur son historique
            // de commandes — la ligne garde son instantané même si le lien
            // relationnel disparaît.
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            // Instantané complet au moment de la commande : un changement
            // ultérieur du nom, du SKU ou du prix du produit ne doit jamais
            // modifier une commande déjà passée.
            $table->string('product_name');
            $table->string('sku');
            $table->decimal('unit_price', 12, 2);
            $table->integer('quantity');
            $table->decimal('subtotal', 12, 2);

            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_unit_price_non_negative CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_subtotal_non_negative CHECK (subtotal >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
