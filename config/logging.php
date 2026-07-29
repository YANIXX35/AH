<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Level
    |--------------------------------------------------------------------------
    |
    | This option defines the minimum log level that should be logged.
    | Available levels: "emergency", "alert", "critical", "error", 
    | "warning", "notice", "info", "debug"
    |
    */

    'level' => env('LOG_LEVEL', 'warning'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'gemini' => [
            'driver' => 'single',
            'path' => storage_path('logs/gemini.log'),
            'level' => 'debug',
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Journal des actions « menu » (navigation zones principales de l’app)
        |--------------------------------------------------------------------------
        | Fichiers : storage/logs/menu-actions-YYYY-MM-DD.log
        */
        'menu_actions' => [
            'driver' => 'daily',
            'path' => storage_path('logs/menu-actions.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => (int) env('LOG_MENU_ACTIONS_DAYS', 90),
            'replace_placeholders' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Journal des flux financiers critiques (OCR / compta / trésorerie)
        |--------------------------------------------------------------------------
        */
        'financial_audit' => [
            'driver' => 'daily',
            'path' => storage_path('logs/financial-audit.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => (int) env('LOG_FINANCIAL_AUDIT_DAYS', 180),
            'replace_placeholders' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Journal des actions OCR
        |--------------------------------------------------------------------------
        */
        'ocr' => [
            'driver' => 'daily',
            'path' => storage_path('logs/ocr.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => (int) env('LOG_OCR_DAYS', 30),
            'replace_placeholders' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Journal des emails envoyés
        |--------------------------------------------------------------------------
        */
        'email' => [
            'driver' => 'daily',
            'path' => storage_path('logs/email.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => (int) env('LOG_EMAIL_DAYS', 30),
            'replace_placeholders' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Journal des uploads de fichiers
        |--------------------------------------------------------------------------
        */
        'uploads' => [
            'driver' => 'daily',
            'path' => storage_path('logs/uploads.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => (int) env('LOG_UPLOADS_DAYS', 30),
            'replace_placeholders' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Journal des appels API externes
        |--------------------------------------------------------------------------
        */
        'api' => [
            'driver' => 'daily',
            'path' => storage_path('logs/api.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => (int) env('LOG_API_DAYS', 30),
            'replace_placeholders' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Journal des exceptions
        |--------------------------------------------------------------------------
        */
        'exceptions' => [
            'driver' => 'daily',
            'path' => storage_path('logs/exceptions.log'),
            'level' => env('LOG_LEVEL', 'error'),
            'days' => (int) env('LOG_EXCEPTIONS_DAYS', 30),
            'replace_placeholders' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Journal des jobs queue
        |--------------------------------------------------------------------------
        */
        'queue' => [
            'driver' => 'daily',
            'path' => storage_path('logs/queue.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => (int) env('LOG_QUEUE_DAYS', 30),
            'replace_placeholders' => true,
        ],

    ],

];
