<?php

namespace App\WhatsApp;

/**
 * The only place phone numbers are masked before ever reaching a log
 * line — used by both MockWhatsAppProvider and SendWhatsAppNotification
 * so the format stays consistent everywhere a phone number is logged.
 */
final class WhatsAppPhoneMasker
{
    public static function mask(string $phone): string
    {
        if (strlen($phone) <= 6) {
            return str_repeat('*', strlen($phone));
        }

        $prefix = substr($phone, 0, 4);
        $suffix = substr($phone, -2);

        return $prefix.str_repeat('*', 6).$suffix;
    }
}
