<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('fee', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        DB::statement('ALTER TABLE delivery_zones ADD CONSTRAINT delivery_zones_fee_non_negative CHECK (fee >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
