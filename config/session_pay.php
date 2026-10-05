<?php

declare(strict_types=1);

return [
    'setting_key' => 'session_pay_rates',
    'currency' => 'EGP',
    'min_duration' => 5,
    'max_duration' => 240,
    'max_amount_minor' => 100000000,
    'defaults' => [
        ['name' => 'الصغير', 'session_type' => 'group', 'duration_minutes' => 35, 'amount' => 3125],
        ['name' => 'الكبير (عادي)', 'session_type' => 'individual', 'duration_minutes' => 25, 'amount' => 2500],
        ['name' => 'الكبير (أطول)', 'session_type' => 'individual', 'duration_minutes' => 40, 'amount' => 3350],
    ],
];
