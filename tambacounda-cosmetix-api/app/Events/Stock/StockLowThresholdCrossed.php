<?php

namespace App\Events\Stock;

use App\Models\Stock;

/**
 * Dispatched by StockObserver only on a genuine crossing — sellable
 * stock (quantity_available - quantity_reserved) was above
 * alert_threshold and is now at or below it. Never dispatched again
 * while stock stays low (no duplicate spam on every subsequent update),
 * but naturally fires again after a later, separate crossing once stock
 * has recovered above the threshold in between. Carries no business
 * logic — the crossing detection itself lives in StockObserver, not here.
 */
class StockLowThresholdCrossed
{
    public function __construct(public readonly Stock $stock) {}
}
