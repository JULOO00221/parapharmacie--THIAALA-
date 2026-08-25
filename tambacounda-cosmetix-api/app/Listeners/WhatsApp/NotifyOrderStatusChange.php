<?php

namespace App\Listeners\WhatsApp;

use App\Events\Order\OrderStatusChanged;
use App\Services\WhatsAppNotificationService;

class NotifyOrderStatusChange
{
    public function __construct(private readonly WhatsAppNotificationService $notifications) {}

    public function handle(OrderStatusChanged $event): void
    {
        $this->notifications->notifyOrderStatusChanged($event->order, $event->to);
    }
}
