<?php

namespace App\WhatsApp;

/**
 * Every WhatsApp template this application can send. Each case's string
 * value is the template NAME as it will eventually need to match a real,
 * pre-approved WhatsApp Business template (lowercase snake_case, WhatsApp's
 * own naming convention) — chosen now to minimize rework later, but the
 * exact approved name/wording is not yet known and may need adjustment
 * once a real WhatsApp Business account exists.
 *
 * ORDER_READY and ORDER_COMPLETED are split into _PICKUP/_DELIVERY
 * variants rather than a single template with a conditional parameter:
 * real WhatsApp Business templates have fixed, pre-approved wording per
 * template, they cannot branch text conditionally within one template.
 */
enum WhatsAppTemplate: string
{
    case ORDER_RECEIVED = 'order_received';
    case ORDER_RECEIVED_MANAGER = 'order_received_manager';
    case ORDER_CONFIRMED = 'order_confirmed';
    case ORDER_PREPARING = 'order_preparing';
    case ORDER_READY_PICKUP = 'order_ready_pickup';
    case ORDER_READY_DELIVERY = 'order_ready_delivery';
    case ORDER_COMPLETED_PICKUP = 'order_completed_pickup';
    case ORDER_COMPLETED_DELIVERY = 'order_completed_delivery';
    case ORDER_CANCELLED = 'order_cancelled';
    case PAYMENT_CONFIRMED = 'payment_confirmed';
    case LOW_STOCK = 'low_stock';
}
