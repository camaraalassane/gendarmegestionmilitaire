<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration permettant à l'application Angular (ou toute autre app)
    | d'appeler l'API de génération de cartes PVC en cross-origin sans blocage.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:4200,http://127.0.0.1:4200,http://localhost:3000')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['X-API-KEY', 'X-Api-Key', 'Content-Type', 'Authorization', 'Accept', 'Origin', 'X-Requested-With'],

    'exposed_headers' => ['*'],

    'max_age' => 86400,

    'supports_credentials' => false,

];
