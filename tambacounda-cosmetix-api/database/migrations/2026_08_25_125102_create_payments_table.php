<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            // 'wave' | 'orange_money' (Orange Money hors périmètre pour le
            // moment — PaymentService::SUPPORTED_PROVIDERS n'autorise que
            // 'wave' aujourd'hui, mais la colonne reste une chaîne libre,
            // même convention que orders.status/payment_method : aucun
            // enum Postgres natif utilisé ailleurs dans ce projet.
            $table->string('provider');

            // Identifiant opaque généré par nous (même politique que
            // orders.order_number) — jamais l'id auto-incrémenté exposé à
            // l'API ou aux URLs.
            $table->string('transaction_id')->unique();

            // Référence renvoyée par le fournisseur (ex. l'id de session
            // Wave) — nulle tant que l'initiation n'a pas abouti, posée
            // dès la réponse de initiate() pour permettre de retrouver ce
            // Payment lors d'un futur webhook réel.
            $table->string('external_reference')->nullable()->unique();

            // Montant et devise épinglés sur order.total au moment de
            // l'initiation — jamais acceptés depuis le frontend, jamais
            // recalculés depuis le fournisseur sans être d'abord comparés
            // à cette valeur (voir PaymentService::markSucceeded).
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('XOF');

            // Transitions et valeurs autorisées imposées par
            // PaymentService, jamais par la colonne elle-même — mêmes
            // conventions que orders.status.
            $table->string('status')->default('pending');

            // Réponse brute du fournisseur (mock ou réel) — jamais de
            // secret fournisseur dedans, uniquement des données
            // fonctionnelles (id de session, wave_launch_url, etc.).
            $table->json('metadata')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->string('failure_reason')->nullable();

            // Expiration de la tentative de paiement (15 min par défaut,
            // voir PaymentService) — distincte de expires_at côté Wave
            // lui-même, qui est stockée dans metadata.
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index('order_id');
            $table->index('status');
            $table->index(['provider', 'status']);
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_non_negative CHECK (amount >= 0)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_currency_xof CHECK (currency = 'XOF')");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
