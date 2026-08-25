<?php

namespace App\Services;

use App\Jobs\SendWhatsAppNotification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Stock;
use App\WhatsApp\WhatsAppTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Centralises every WhatsApp notification this application sends — the
 * only class that knows which WhatsAppTemplate/phone/parameters a given
 * domain fact maps to. Never called directly by OrderService,
 * PaymentService or Filament: it's only ever invoked by the WhatsApp
 * listeners reacting to the events those services dispatch (see
 * app/Listeners/WhatsApp). Never sends synchronously — every call ends
 * in a SendWhatsAppNotification::dispatch(), so a slow or failing
 * WhatsApp provider can never add latency to POST /orders or
 * POST /payments, nor affect their outcome.
 */
class WhatsAppNotificationService
{
    public function notifyOrderReceived(Order $order): void
    {
        $this->dispatch($order->customer_phone, WhatsAppTemplate::ORDER_RECEIVED, [
            'customer_name' => $order->customer_name,
            'order_number' => $order->order_number,
            'total' => (string) $order->total,
            'fulfillment_mode' => $this->fulfillmentModeLabel($order),
        ]);

        $managerPhone = $this->managerPhone();
        if ($managerPhone === null) {
            return;
        }

        $this->dispatch($managerPhone, WhatsAppTemplate::ORDER_RECEIVED_MANAGER, [
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'total' => (string) $order->total,
            'fulfillment_mode' => $this->fulfillmentModeLabel($order),
        ]);
    }

    /**
     * $to is a raw orders.status value (OrderService::ALLOWED_TRANSITIONS
     * vocabulary) — this method is the ONLY place that maps a status
     * string to a WhatsAppTemplate; OrderService itself never knows
     * templates exist.
     */
    public function notifyOrderStatusChanged(Order $order, string $to): void
    {
        $template = match ($to) {
            'confirmed' => WhatsAppTemplate::ORDER_CONFIRMED,
            'preparing' => WhatsAppTemplate::ORDER_PREPARING,
            'ready' => $order->is_pickup ? WhatsAppTemplate::ORDER_READY_PICKUP : WhatsAppTemplate::ORDER_READY_DELIVERY,
            'delivered' => $order->is_pickup ? WhatsAppTemplate::ORDER_COMPLETED_PICKUP : WhatsAppTemplate::ORDER_COMPLETED_DELIVERY,
            'cancelled' => WhatsAppTemplate::ORDER_CANCELLED,
            default => null,
        };

        if ($template === null) {
            return;
        }

        $this->dispatch($order->customer_phone, $template, [
            'customer_name' => $order->customer_name,
            'order_number' => $order->order_number,
            'total' => (string) $order->total,
        ]);
    }

    public function notifyPaymentConfirmed(Payment $payment): void
    {
        $order = $payment->order;

        $this->dispatch($order->customer_phone, WhatsAppTemplate::PAYMENT_CONFIRMED, [
            'customer_name' => $order->customer_name,
            'order_number' => $order->order_number,
            'total' => (string) $payment->amount,
        ]);
    }

    public function notifyLowStock(Stock $stock): void
    {
        $managerPhone = $this->managerPhone();
        if ($managerPhone === null) {
            return;
        }

        $product = $stock->product;
        $sellable = max(0, $stock->quantity_available - $stock->quantity_reserved);

        $this->dispatch($managerPhone, WhatsAppTemplate::LOW_STOCK, [
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => (string) $sellable,
            'threshold' => (string) $stock->alert_threshold,
        ]);
    }

    private function fulfillmentModeLabel(Order $order): string
    {
        return $order->is_pickup ? 'Retrait en boutique' : 'Livraison';
    }

    /**
     * Jamais codé en dur — toujours WHATSAPP_MANAGER_PHONE. Absent :
     * aucun envoi, journalisé pour rester diagnosticable sans jamais
     * faire échouer l'appelant (créer une commande / franchir un seuil
     * de stock doit toujours réussir même sans numéro gérant configuré).
     */
    private function managerPhone(): ?string
    {
        $phone = config('services.whatsapp.manager_phone');

        if (empty($phone)) {
            Log::warning('whatsapp.manager_phone_not_configured');

            return null;
        }

        return $phone;
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function dispatch(string $phone, WhatsAppTemplate $template, array $parameters): void
    {
        // Un UUID frais à CHAQUE dispatch logique — jamais dérivé de
        // données stables (order_id/template) : un futur nouveau
        // franchissement de seuil ou une future transition doit toujours
        // pouvoir émettre sa propre notification, jamais être bloqué par
        // la clé d'une notification précédente et sans rapport. Cette clé
        // ne protège que contre les RETRIES du même job (elle est
        // conservée telle quelle par Laravel sur chaque tentative de CE
        // job précis), jamais contre un événement métier différent.
        $dedupeKey = (string) Str::uuid();

        SendWhatsAppNotification::dispatch($phone, $template->value, $parameters, $dedupeKey);
    }
}
