<?php

namespace App\WhatsApp;

use RuntimeException;

/**
 * Sole place that decides which concrete WhatsAppProviderInterface
 * handles outgoing messages — SendWhatsAppNotification never branches on
 * "if provider === 'mock'" itself. Swapping the mock for a real WhatsApp
 * Cloud API integration later is a one-line change here (plus writing
 * the real provider class); nothing else in the notification stack moves.
 */
class WhatsAppProviderFactory
{
    public function for(): WhatsAppProviderInterface
    {
        // WHATSAPP_MOCK est le garde-fou prioritaire, vérifié AVANT
        // WHATSAPP_PROVIDER : même si ce dernier était un jour mal
        // configuré sur une valeur réaliste, mock=true (défaut) force
        // quand même le comportement simulé — aucun appel réseau
        // possible tant que ce flag n'est pas explicitement désactivé.
        // Résolu via l'interface (liée à MockWhatsAppProvider dans
        // AppServiceProvider) plutôt que la classe concrète, pour que
        // les tests puissent substituer un faux provider.
        if (config('services.whatsapp.mock')) {
            return app(WhatsAppProviderInterface::class);
        }

        $provider = (string) config('services.whatsapp.provider');

        return match ($provider) {
            // Pas encore de provider réel écrit (aucun accès WhatsApp
            // Business/Cloud API) — cet arm n'a donc aucune branche non
            // mock aujourd'hui.
            default => throw new RuntimeException("Fournisseur WhatsApp non pris en charge : {$provider}."),
        };
    }
}
