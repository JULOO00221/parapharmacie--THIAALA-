<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();
            $table->integer('quantity_available')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->integer('alert_threshold')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'store_id']);
        });

        DB::statement('ALTER TABLE stocks ADD CONSTRAINT stocks_quantity_available_non_negative CHECK (quantity_available >= 0)');
        DB::statement('ALTER TABLE stocks ADD CONSTRAINT stocks_quantity_reserved_non_negative CHECK (quantity_reserved >= 0)');
        DB::statement('ALTER TABLE stocks ADD CONSTRAINT stocks_alert_threshold_non_negative CHECK (alert_threshold >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
