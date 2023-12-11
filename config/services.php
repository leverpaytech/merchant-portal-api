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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'vfd' => [
        'consumerkey' => env('VFD_CONSUMER_KEY'),
        'consumerSecretkey' => env('VFD_CONSUMER_SECRET'),
        'accessToken' => env('VFD_ACCESS_TOKEN'),
        'authurl' => env('VFD_AUTH_URL'),
        'baseurl' => env('VFD_BASE_URL'),
        'walletCredentials' => env('VFD_WALLET_CREDENTIALS'),
        'onboarding' => env('VFD_ONBOARDING_URL')
    ],
    'etherscan' => [
        'api_key' => env('ETHERSCAN_API_KEY'),
        'api_url' => env('ETHERSCAN_API_URL'),
    ],
    
];
