<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe (D3, D7)
    |--------------------------------------------------------------------------
    |
    | Modo test durante todo el desarrollo: claves `pk_test_` y `sk_test_`,
    | tarjetas de prueba y sin contrato bancario.
    |
    | La secreta y la del webhook no salen nunca del servidor. La publicable
    | si viaja al navegador, y no pasa nada: esta hecha para eso.
    |
    | `webhook.secret` lo da `stripe listen` en local y el panel de Stripe en
    | produccion. Sin el no se puede verificar la firma, y sin firma cualquiera
    | podria mandarnos un «pago confirmado».
    |
    */

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
    ],

];
