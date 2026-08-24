<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Identifiant public non séquentiel et difficilement devinable
            // (généré par OrderService, pas par la base) — jamais l'id
            // auto-incrémenté, pour ne pas exposer le volume de commandes
            // ni permettre de deviner le numéro d'une autre commande.
            $table->string('order_number')->unique();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('store_id')
                ->constrained('stores')
                ->restrictOnDelete();
            $table->foreignId('delivery_zone_id')
                ->nullable()
                ->constrained('delivery_zones')
                ->restrictOnDelete();

            // Coordonnées client : toujours renseignées, y compris pour une
            // commande authentifiée. C'est un instantané pris au moment du
            // checkout, jamais une lecture différée de users — si le profil
            // change après coup, la commande garde les coordonnées d'origine.
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();

            // Retrait en boutique : pas de fausse "zone" à frais nul, un
            // booléen dédié. delivery_zone_id/delivery_address restent donc
            // nullable et n'ont de sens que lorsque is_pickup est false —
            // règle appliquée au niveau applicatif (OrderService), pas via
            // une contrainte CHECK inter-colonnes (même principe que
            // compare_at_price sur products : complexité disproportionnée
            // pour ce que cette phase requiert).
            $table->boolean('is_pickup')->default(false);
            $table->text('delivery_address')->nullable();
            $table->text('notes')->nullable();

            // Montants toujours recalculés et écrits par Laravel — jamais
            // acceptés depuis le frontend.
            $table->decimal('subtotal', 12, 2);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            // Chaîne simple plutôt qu'un enum Postgres natif : aucun enum
            // natif n'est utilisé ailleurs dans le projet, et un enum
            // natif rend coûteux l'ajout d'une valeur plus tard (ALTER
            // TYPE). Les valeurs autorisées et les transitions sont
            // imposées par OrderService, pas par la colonne elle-même.
            $table->string('status')->default('pending');

            // Paiement à la livraison / en boutique uniquement pour cette
            // phase — aucune intégration Wave/Orange Money ici.
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('pending');

            // Obligatoire et unique : garantit qu'un double clic ou une
            // requête rejouée sur POST /orders ne peut jamais créer deux
            // commandes distinctes pour la même tentative de checkout.
            $table->string('idempotency_key')->unique();

            $table->timestamps();

            $table->index('status');
            $table->index('payment_status');
            $table->index('user_id');
            $table->index('store_id');
            $table->index('customer_phone');
        });

        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_subtotal_non_negative CHECK (subtotal >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_delivery_fee_non_negative CHECK (delivery_fee >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_non_negative CHECK (total >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
