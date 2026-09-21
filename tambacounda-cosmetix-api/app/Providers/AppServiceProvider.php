<?php

namespace App\Providers;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\Stock;
use App\Models\Tag;
use App\Observers\CatalogCacheObserver;
use App\Observers\StockObserver;
use App\Services\ImageSourcing\OpenBeautyFactsClient;
use App\WhatsApp\MockWhatsAppProvider;
use App\WhatsApp\WhatsAppProviderInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        // Partagé, car ce client porte l'état de la limite de débit d'Open
        // Beauty Facts (dernier appel émis) ainsi que le compte de requêtes et
        // les erreurs affichés en fin de commande. Deux instances se
        // croiraient seules et dépasseraient le quota autorisé.
        $this->app->singleton(OpenBeautyFactsClient::class);
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

        // Toute écriture sur une donnée que le frontend met en cache vide
        // son cache du catalogue (job dédoublonné, après commit) — voir
        // CatalogCacheObserver et RevalidateFrontendCatalog.
        foreach ([Product::class, ProductCategory::class, Brand::class, Tag::class, ProductImage::class, Stock::class] as $model) {
            $model::observe(CatalogCacheObserver::class);
        }

        // Garde-fou global du groupe api (voir bootstrap/app.php). Clé =
        // IP client résolue depuis X-Forwarded-For par TrustProxies — pas
        // l'IP du proxy. Les routes sensibles (auth, commandes, paiements)
        // gardent leur propre throttle plus strict en plus de celui-ci.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(1000)->by($request->ip()));
    }
}
