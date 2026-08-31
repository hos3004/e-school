<?php

declare(strict_types=1);

/*
| مراقبة طوابير Horizon.
|
| بدون هذا الملف يراقب Horizon طابور `default` فقط، بينما إشعارات
| Notifications تُوزَّع على طابور `notifications` (config/notifications.php)
| فتبقى سطور الصندوق معلّقة. نضمّن الطوابير المعروفة هنا.
*/

return [

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_DASHBOARD_PATH', 'horizon'),

    'use' => 'default',

    'allow_notifications' => true,

    'trim' => [
        'recent' => (int) env('HORIZON_TRIM_RECENT', 60),
        'pending' => (int) env('HORIZON_TRIM_PENDING', 60),
        'completed' => (int) env('HORIZON_TRIM_COMPLETED', 60),
        'recent_failed' => (int) env('HORIZON_TRIM_RECENT_FAILED', 10080),
        'failed' => (int) env('HORIZON_TRIM_FAILED', 10080),
        'monitored' => (int) env('HORIZON_TRIM_MONITORED', 10080),
    ],

    'silenced' => [],

    'default' => 'supervisor-1',

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default', 'notifications'],
                'balance' => 'auto',
                'maxProcesses' => 2,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 128,
                'tries' => 1,
                'timeout' => 60,
                'nice' => 0,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default', 'notifications'],
                'balance' => 'auto',
                'maxProcesses' => 2,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 128,
                'tries' => 1,
                'timeout' => 60,
                'nice' => 0,
            ],
        ],
    ],
];
