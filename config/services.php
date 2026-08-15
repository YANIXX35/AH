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

    'cinetpay' => [
        'enabled' => (bool) env('CINETPAY_ENABLED', false),
        'api_key' => env('CINETPAY_API_KEY'),
        'site_id' => env('CINETPAY_SITE_ID'),
        'secret_key' => env('CINETPAY_SECRET_KEY'),
        'checkout_url' => env('CINETPAY_CHECKOUT_URL', 'https://api-checkout.cinetpay.com/v2/payment'),
        'check_url' => env('CINETPAY_CHECK_URL', 'https://api-checkout.cinetpay.com/v2/payment/check'),
        'currency' => env('CINETPAY_CURRENCY', 'XOF'),
        'channels' => env('CINETPAY_CHANNELS', 'ALL'),
        'lang' => env('CINETPAY_LANG', 'fr'),
        'subscription_amount' => (int) env('CINETPAY_SUBSCRIPTION_AMOUNT', 15000),
        'premium_days' => (int) env('CINETPAY_PREMIUM_DAYS', 30),
        'customer_country' => env('CINETPAY_CUSTOMER_COUNTRY', 'CI'),
        'timeout' => (int) env('CINETPAY_TIMEOUT', 30),
        'simulate_when_unconfigured' => (bool) env('CINETPAY_SIMULATE_WHEN_UNCONFIGURED', true),
        'mobile_methods' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'CINETPAY_MOBILE_METHODS',
            'MOBILE_MONEY,WAVE,ORANGE_MONEY,MTN_MOMO,MOOV_MONEY,CREDIT_CARD'
        ))))),
    ],

    'fedapay' => [
        'sandbox' => [
            'enabled' => (bool) env('FEDAPAY_SANDBOX_ENABLED', env('PAWAPAY_SANDBOX_ENABLED', false)),
            'base_url' => env('FEDAPAY_SANDBOX_BASE_URL', env('PAWAPAY_SANDBOX_BASE_URL', 'https://sandbox-api.fedapay.com')),
            'api_key' => env('FEDAPAY_SANDBOX_API_KEY', env('PAWAPAY_SANDBOX_API_KEY')),
            'public_key' => env('FEDAPAY_SANDBOX_PUBLIC_KEY'),
            'payment_page_url' => env('FEDAPAY_SANDBOX_PAYMENT_PAGE_URL'),
            'payment_page_name' => env('FEDAPAY_SANDBOX_PAYMENT_PAGE_NAME', 'Page de paiement FedaPay'),
            'payment_page_amount' => env('FEDAPAY_SANDBOX_PAYMENT_PAGE_AMOUNT', '15000'),
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

    'ocr_space' => [
        'endpoint' => env('OCR_SPACE_ENDPOINT', 'https://api.ocr.space/parse/image'),
        'api_key' => env('OCR_SPACE_API_KEY'),
        'language' => env('OCR_SPACE_LANGUAGE', 'fre'),
        'engine' => (int) env('OCR_SPACE_ENGINE', 2),
        'timeout' => (int) env('OCR_SPACE_TIMEOUT', 60),
        'is_table' => (bool) env('OCR_SPACE_IS_TABLE', true),
        'scale' => (bool) env('OCR_SPACE_SCALE', true),
        'detect_orientation' => (bool) env('OCR_SPACE_DETECT_ORIENTATION', true),
        'max_file_size_kb' => (int) env('OCR_SPACE_MAX_FILE_SIZE_KB', 1024),
    ],

    'paddle_ocr' => [
        'enabled' => (bool) env('PADDLE_OCR_ENABLED', false),
        'python_path' => env('PADDLE_OCR_PYTHON_PATH', 'python'),
        'runner_path' => env('PADDLE_OCR_RUNNER_PATH', base_path('paddle_ocr_runner.py')),
        'timeout' => (int) env('PADDLE_OCR_TIMEOUT', 180),
        'preferred_device' => env('PADDLE_OCR_PREFERRED_DEVICE', 'gpu'),
        'fallback_to_cpu' => (bool) env('PADDLE_OCR_FALLBACK_TO_CPU', true),
        'page_num' => (int) env('PADDLE_OCR_PAGE_NUM', 0),
        'language' => env('PADDLE_OCR_LANGUAGE', 'fr'),
        'max_file_size_kb' => (int) env('PADDLE_OCR_MAX_FILE_SIZE_KB', 20480),
    ],

    'huggingface' => [
        'token' => env('HUGGINGFACE_TOKEN'),
        'model' => env('HUGGINGFACE_MODEL', 'meta-llama/Llama-3.1-8B-Instruct'),
        'base_url' => env('HUGGINGFACE_BASE_URL', 'https://router.huggingface.co/v1'),
        'timeout' => (int) env('HUGGINGFACE_TIMEOUT', 45),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 45),
    ],

    'posthog' => [
        'key' => env('POSTHOG_KEY'),
        'host' => env('POSTHOG_HOST', 'https://eu.i.posthog.com'),
    ],

];
