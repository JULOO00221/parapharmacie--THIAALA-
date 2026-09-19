<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proxies de confiance
    |--------------------------------------------------------------------------
    |
    | Lu à chaque requête par le middleware global TrustProxies. Quand un
    | proxy est de confiance, $request->ip() renvoie l'IP du client extraite
    | de X-Forwarded-For au lieu de l'IP TCP du proxy. Tous les throttles
    | (global « api » comme auth/checkout) s'appuient sur $request->ip() :
    | sans cette configuration, tous les visiteurs derrière le même proxy
    | partagent un seul compteur.
    |
    | '*' fait confiance à n'importe quel appelant : un client qui atteint
    | l'API directement peut alors forger X-Forwarded-For et contourner les
    | throttles login/register/checkout. En production, renseigner les
    | IP/CIDR réels du proxy (liste séparée par des virgules).
    |
    */

    'proxies' => env('TRUSTED_PROXIES', '*'),

];
