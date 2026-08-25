<?php

namespace App\WhatsApp;

/**
 * Every real or mock WhatsApp provider implements this — nothing else in
 * the notification stack (WhatsAppNotificationService, SendWhatsAppNotification,
 * listeners) ever knows which one it's talking to. Swapping the mock for
 * a real WhatsApp Cloud API integration later means writing one class
 * implementing this interface and pointing WhatsAppProviderFactory at it
 * (config('services.whatsapp.provider')) — nothing else changes.
 */
interface WhatsAppProviderInterface
{
    /**
     * @param  array<string, string>  $parameters  Named template variables — the real WhatsApp Business API expects positional {{1}}, {{2}}... variables; the exact mapping is deferred until real, approved templates exist (see the Phase audit report).
     */
    public function sendTemplate(string $phone, string $template, array $parameters): WhatsAppMessageResult;
}
