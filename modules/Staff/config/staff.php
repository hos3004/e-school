<?php

declare(strict_types=1);

return [
    'account' => [
        'teacher_role' => env('STAFF_TEACHER_ROLE', 'teacher'),
        'code_prefix' => env('STAFF_CODE_PREFIX', 'TCH'),
    ],

    'currency' => [
        'default' => env('STAFF_DEFAULT_CURRENCY', 'EGP'),
        'supported' => ['EGP', 'USD', 'EUR'],
    ],
];
