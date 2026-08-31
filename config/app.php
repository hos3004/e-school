<?php

declare(strict_types=1);

return [

    'name' => env('APP_NAME', 'E-School'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),

    'timezone' => 'UTC',

    'locale' => env('APP_LOCALE', 'ar'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => 'ar_EG',

    'supported_locales' => array_map(
        'trim',
        explode(',', (string) env('APP_SUPPORTED_LOCALES', 'ar,en')),
    ),

    'rtl_locales' => ['ar'],

    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
];
