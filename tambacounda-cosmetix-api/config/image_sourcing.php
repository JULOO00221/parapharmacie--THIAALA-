<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Open Beauty Facts
    |--------------------------------------------------------------------------
    |
    | Open Beauty Facts impose un User-Agent qui identifie l'application, au
    | format « AppName/Version (contact) ». Une requête anonyme est traitée
    | comme un robot et peut être bloquée.
    |
    | Limites de débit publiées par le projet (par adresse IP) :
    |   - 15 requêtes/minute pour la lecture d'un produit,
    |   - 10 requêtes/minute pour la recherche.
    | On se cale volontairement en dessous.
    |
    */

    'open_beauty_facts' => [
        'base_url' => env('OBF_BASE_URL', 'https://world.openbeautyfacts.org'),

        'user_agent' => env(
            'OBF_USER_AGENT',
            'TambacoundaCosmetix/1.0 (https://thiaala.sn; '.env('OBF_CONTACT_EMAIL', 'contact@thiaala.sn').')',
        ),

        'timeout' => env('OBF_TIMEOUT', 45),

        // Temps laissé au serveur public pour accepter la connexion. Les
        // 10 secondes par défaut de Laravel sont trop courtes pour lui.
        'connect_timeout' => env('OBF_CONNECT_TIMEOUT', 30),

        // Requêtes par minute que l'on s'autorise, en deçà des limites publiées.
        'rate_limit' => [
            'product' => env('OBF_RATE_PRODUCT', 12),
            'search' => env('OBF_RATE_SEARCH', 8),
        ],

        // Licence des photos, telle qu'annoncée sur https://world.openbeautyfacts.org/data
        'image_license' => [
            'code' => 'CC-BY-SA-3.0',
            'name' => 'Creative Commons Attribution - Partage dans les mêmes conditions 3.0',
            'url' => 'https://creativecommons.org/licenses/by-sa/3.0/deed.fr',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Traitement des images
    |--------------------------------------------------------------------------
    */

    'image' => [
        'width' => 800,
        'max_bytes' => 80 * 1024,
        // Au-delà, l'image n'est pas une photo de produit mais la tranche
        // d'une boîte ou un bandeau d'étiquette : inutilisable en vignette.
        'max_aspect_ratio' => 3.0,
        // Qualités WebP essayées dans l'ordre jusqu'à passer sous max_bytes.
        'quality_steps' => [82, 74, 66, 58, 50],
        'candidate_directory' => 'product-image-candidates',
        'approved_directory' => 'products',
        // Garde-fou sur le téléchargement brut, avant redimensionnement.
        'max_download_bytes' => 12 * 1024 * 1024,
    ],

    /*
    |--------------------------------------------------------------------------
    | Correspondance
    |--------------------------------------------------------------------------
    |
    | En dessous de min_confidence, la correspondance est jugée trop faible :
    | le produit est reporté dans le CSV des non-appariés plutôt qu'enregistré
    | comme candidat à valider.
    |
    */

    'matching' => [
        'min_confidence' => env('OBF_MIN_CONFIDENCE', 55),
        // Nombre de résultats de recherche examinés par produit.
        'candidates_per_search' => 5,
    ],

];
