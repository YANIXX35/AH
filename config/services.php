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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    ],

    'fedapay' => [
        'sandbox' => [
            'enabled' => (bool) env('FEDAPAY_SANDBOX_ENABLED', env('PAWAPAY_SANDBOX_ENABLED', false)),
            'base_url' => env('FEDAPAY_SANDBOX_BASE_URL', env('PAWAPAY_SANDBOX_BASE_URL', 'https://sandbox-api.fedapay.com')),
            'api_key' => env('FEDAPAY_SANDBOX_API_KEY', env('PAWAPAY_SANDBOX_API_KEY')),
            'public_key' => env('FEDAPAY_SANDBOX_PUBLIC_KEY'),
            'payment_page_url' => env('FEDAPAY_SANDBOX_PAYMENT_PAGE_URL'),
            'payment_page_name' => env('FEDAPAY_SANDBOX_PAYMENT_PAGE_NAME', 'Page de paiement FedaPay'),
            'payment_page_amount' => env('FEDAPAY_SANDBOX_PAYMENT_PAGE_AMOUNT', '100'),
            'payment_page_currency' => env('FEDAPAY_SANDBOX_PAYMENT_PAGE_CURRENCY', 'CFA'),
            'payment_page_method' => env('FEDAPAY_SANDBOX_PAYMENT_PAGE_METHOD', 'WAVE'),
            'payment_page_country' => env('FEDAPAY_SANDBOX_PAYMENT_PAGE_COUNTRY', 'CIV'),
            'fallback_to_payment_page' => (bool) env('FEDAPAY_SANDBOX_FALLBACK_TO_PAYMENT_PAGE', true),
            'mobile_methods' => array_values(array_filter(array_map('trim', explode(',', (string) env(
                'FEDAPAY_SANDBOX_MOBILE_METHODS',
                'WAVE,MTN_MOMO,ORANGE_MONEY,MOOV_MONEY,CELTIIS_MONEY,FLOOZ'
            ))))),
        ],
    ],

    'stripe' => [
        'enabled' => (bool) env('STRIPE_ENABLED', false),
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'XOF'),
        'payout_currency' => env('STRIPE_PAYOUT_CURRENCY', env('STRIPE_CURRENCY', 'XOF')),
        'payment_link_url' => env('STRIPE_PAYMENT_LINK_URL'),
    ],

];
