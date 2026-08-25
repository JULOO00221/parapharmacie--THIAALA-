<?php

namespace App\Observers;

use App\Events\Stock\StockLowThresholdCrossed;
use App\Models\Stock;

/**
 * The single choke point for "did sellable stock just cross below its
 * alert threshold" — covers every way a Stock row can change (order
 * confirmation/cancellation via OrderService, manual admin edits via
 * either Filament stock form, and product creation/CSV import via
 * ProductService), since all of them go through Eloquent and therefore
 * fire this observer. No caller needs to remember to check this
 * themselves.
 *
 * Sellable stock mirrors ProductService::availability()'s own
 * definition exactly: quantity_available - quantity_reserved, clamped
 * at 0, compared to alert_threshold.
 */
class StockObserver
{
    public function created(Stock $stock): void
    {
        // Juste après Stock::create(), tout attribut omis du payload
        // reste `null` en mémoire même si la colonne a un défaut DB
        // (0) — non encore relu depuis la base à ce stade.
        if ($this->isLow($stock->quantity_available, $stock->quantity_reserved, $stock->alert_threshold)) {
            event(new StockLowThresholdCrossed($stock));
        }
    }

    public function updated(Stock $stock): void
    {
        if (! $stock->wasChanged(['quantity_available', 'quantity_reserved', 'alert_threshold'])) {
            return;
        }

        $wasLow = $this->isLow(
            $stock->getOriginal('quantity_available') ?? $stock->quantity_available,
            $stock->getOriginal('quantity_reserved') ?? $stock->quantity_reserved,
            $stock->getOriginal('alert_threshold') ?? $stock->alert_threshold,
        );

        $isLowNow = $this->isLow($stock->quantity_available, $stock->quantity_reserved, $stock->alert_threshold);

        // Uniquement le franchissement réel (was above, now at/below) —
        // jamais un doublon tant que le stock reste bas, mais un futur
        // nouveau franchissement après un retour au-dessus du seuil
        // redéclenche naturellement une alerte (wasLow sera à nouveau
        // false à ce moment-là).
        if (! $wasLow && $isLowNow) {
            event(new StockLowThresholdCrossed($stock));
        }
    }

    private function isLow(?int $available, ?int $reserved, ?int $threshold): bool
    {
        if ($threshold === null) {
            return false;
        }

        return max(0, ($available ?? 0) - ($reserved ?? 0)) <= $threshold;
    }
}
