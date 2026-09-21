<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une photo venue d'une source externe est soumise à une licence (CC-BY-SA
 * pour Open Beauty Facts) qui impose d'afficher son attribution. Ces colonnes
 * accompagnent l'image partout où elle est servie : sans elles, l'image ne
 * peut pas être publiée légalement. Elles restent NULL pour les photos
 * produites en interne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('source')->nullable()->after('is_primary');
            $table->string('source_url', 1024)->nullable()->after('source');
            $table->string('license_code')->nullable()->after('source_url');
            $table->string('license_url', 512)->nullable()->after('license_code');
            $table->string('attribution', 512)->nullable()->after('license_url');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_url', 'license_code', 'license_url', 'attribution']);
        });
    }
};
