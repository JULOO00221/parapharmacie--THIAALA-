<?php

namespace App\Listeners\WhatsApp;

use App\Events\Stock\StockLowThresholdCrossed;
use App\Services\WhatsAppNotificationService;

class NotifyLowStock
{
    public function __construct(private readonly WhatsAppNotificationService $notifications) {}

    public function handle(StockLowThresholdCrossed $event): void
    {
        $this->notifications->notifyLowStock($event->stock);
    }
}
