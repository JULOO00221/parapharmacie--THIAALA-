<?php

namespace App\Listeners\WhatsApp;

use App\Events\Order\OrderCreated;
use App\Services\WhatsAppNotificationService;

class NotifyOrderReceived
{
    public function __construct(private readonly WhatsAppNotificationService $notifications) {}

    public function handle(OrderCreated $event): void
    {
        $this->notifications->notifyOrderReceived($event->order);
    }
}
