<?php

namespace App\Listeners\WhatsApp;

use App\Events\Payment\PaymentConfirmed;
use App\Services\WhatsAppNotificationService;

class NotifyPaymentConfirmed
{
    public function __construct(private readonly WhatsAppNotificationService $notifications) {}

    public function handle(PaymentConfirmed $event): void
    {
        $this->notifications->notifyPaymentConfirmed($event->payment);
    }
}
