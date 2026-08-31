<?php

declare(strict_types=1);

return [
    'account' => [
        'teacher_role' => (string) Illuminate\Support\Env::get('STAFF_TEACHER_ROLE', 'teacher'),
        'code_prefix' => (string) Illuminate\Support\Env::get('STAFF_CODE_PREFIX', 'TCH'),
    ],

    'currency' => [
        'default' => (string) Illuminate\Support\Env::get('STAFF_DEFAULT_CURRENCY', 'EGP'),
        'supported' => ['EGP', 'USD', 'EUR'],
    ],
];
