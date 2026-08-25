<?php

namespace App\Providers;

use App\Models\Stock;
use App\Observers\StockObserver;
use App\WhatsApp\MockWhatsAppProvider;
use App\WhatsApp\WhatsAppProviderInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Résolu via l'interface (jamais la classe concrète directement)
        // uniquement pour que les tests puissent substituer un faux
        // provider avec $this->app->instance(WhatsAppProviderInterface::class, ...)
        // — une liaison instance() prime toujours sur ce bind(). Le
        // garde-fou WHATSAPP_MOCK reste dans WhatsAppProviderFactory,
        // pas ici.
        $this->app->bind(WhatsAppProviderInterface::class, MockWhatsAppProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Seul déclencheur de StockLowThresholdCrossed — couvre toute
        // mutation de Stock, quelle que soit son origine (OrderService,
        // édition Filament, import produit), voir StockObserver.
        Stock::observe(StockObserver::class);
    }
}
