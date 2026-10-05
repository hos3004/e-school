<?php

declare(strict_types=1);

/*
| إعدادات موديول Sessions — التقنية فقط. سياسات المدرسة الرقمية تعيش
| في config/scheduling.php و config/discipline.php و config/payroll.php.
*/

return [
    'admin_hub' => [
        'max_items' => 25,
    ],

    'pagination' => [
        'per_page' => 15,
    ],

    'reporting' => [
        'max_items' => 10001,
    ],
];
