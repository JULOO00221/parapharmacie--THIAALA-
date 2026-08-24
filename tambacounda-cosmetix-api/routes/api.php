<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DeliveryZoneController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\TagController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{slug}', [ProductController::class, 'show'])->name('products.show');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/{slug}', [CategoryController::class, 'show'])->name('categories.show');

    Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
    Route::get('brands/{slug}', [BrandController::class, 'show'])->name('brands.show');

    Route::get('tags', [TagController::class, 'index'])->name('tags.index');

    // Données de référence pour le checkout (sélecteur boutique / zone de
    // livraison) — mêmes conventions que brands/tags : liste non paginée
    // (petit référentiel borné), filtrée aux éléments actifs.
    Route::get('stores', [StoreController::class, 'index'])->name('stores.index');
    Route::get('delivery-zones', [DeliveryZoneController::class, 'index'])->name('delivery-zones.index');

    Route::prefix('orders')->name('orders.')->group(function () {
        // Visiteur ET utilisateur authentifié autorisés — aucun
        // middleware auth:sanctum ici, le contrôleur résout l'utilisateur
        // optionnel lui-même via le guard sanctum.
        // Préfixes explicites et distincts sur chaque throttle : Laravel
        // calcule la clé de cache du throttle numérique uniquement à
        // partir de la signature (IP ou utilisateur), sans jamais inclure
        // maxAttempts/decayMinutes — deux throttles appliqués à la même
        // route (ex. le throttle global de l'api group et celui-ci)
        // partageraient donc le MÊME compteur sans préfixe, et
        // s'incrémenteraient mutuellement à chaque requête.
        Route::post('/', [OrderController::class, 'store'])
            ->middleware('throttle:5,1,orders-create')
            ->name('store');

        // Liste : réservée aux utilisateurs authentifiés, toujours
        // scopée à leurs propres commandes.
        Route::get('/', [OrderController::class, 'index'])
            ->middleware(['auth:sanctum', 'throttle:30,1,orders-read'])
            ->name('index');

        // Consultation unitaire : invité (preuve par téléphone) ou
        // propriétaire authentifié — voir OrderController::canView().
        Route::get('{orderNumber}', [OrderController::class, 'show'])
            ->middleware('throttle:30,1,orders-read')
            ->name('show');
    });

    Route::prefix('auth')->name('auth.')->group(function () {
        // Préfixe distinct (voir le commentaire sur le groupe orders
        // ci-dessus) : sans lui, ce throttle partagerait sa clé de cache
        // avec le throttle global de l'api group maintenant actif, et la
        // limite réelle de 6/min serait atteinte deux fois plus vite.
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:6,1,auth')
            ->name('register');
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:6,1,auth')
            ->name('login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
        });
    });
});
