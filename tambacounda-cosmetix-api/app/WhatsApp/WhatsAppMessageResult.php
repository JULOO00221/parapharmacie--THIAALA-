<?php

namespace App\WhatsApp;

/**
 * What a provider hands back right after attempting to send a template
 * message. Field names deliberately generic (never tied to Wave/Meta's
 * actual response shape) so a future real provider (WhatsApp Cloud API)
 * only needs to map its own response onto this same DTO — nothing else
 * in the notification stack changes.
 */
final readonly class WhatsAppMessageResult
{
    public function __construct(
        public bool $success,
        public string $status,
        public ?string $externalId,
        public ?string $error = null,
    ) {}
}
