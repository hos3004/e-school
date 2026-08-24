<?php

declare(strict_types=1);

return [

    'default' => env('FILESYSTEM_DISK', 'r2'),

    'disks' => [

        // لا يُستخدم إلا عبر scripts/test-isolated.php؛ لكل تشغيل root مستقل.
        'test_isolated' => [
            'driver' => 'local',
            'root' => storage_path('framework/testing/'.env('TEST_RUN_TOKEN', 'unconfigured')),
            'serve' => false,
            'throw' => true,
        ],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        // التخزين الأساسي: Cloudflare R2 متوافق مع S3 وبلا رسوم خروج.
        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'url' => env('R2_URL'),
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
            'throw' => true,
        ],

        // تسجيلات الحصص أثناء مدة الاحتفاظ (30 يومًا).
        'recordings' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_RECORDINGS_BUCKET', env('R2_BUCKET')),
            'endpoint' => env('R2_ENDPOINT'),
            'root' => 'recordings',
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
            'throw' => true,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
