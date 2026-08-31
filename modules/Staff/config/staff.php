<?php

declare(strict_types=1);
use Illuminate\Support\Env;

return [
    'account' => [
        'teacher_role' => (string) Env::get('STAFF_TEACHER_ROLE', 'teacher'),
        'code_prefix' => (string) Env::get('STAFF_CODE_PREFIX', 'TCH'),
    ],

    'currency' => [
        'default' => (string) Env::get('STAFF_DEFAULT_CURRENCY', 'EGP'),
        'supported' => ['EGP', 'USD', 'EUR'],
    ],
];
