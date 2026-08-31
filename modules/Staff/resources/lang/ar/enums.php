<?php

declare(strict_types=1);

return [
    'employment_type' => [
        'full_time' => 'دوام كامل',
        'part_time' => 'دوام جزئي',
        'hourly' => 'بالساعة',
        'contractor' => 'متعاقد',
    ],
    'contract_basis' => [
        'salary' => 'راتب ثابت',
        'per_session' => 'لكل حصة',
        'hybrid' => 'راتب وحصص',
    ],
    'rate_scope' => [
        'course' => 'كورس محدد',
        'program' => 'برنامج محدد',
        'session_type' => 'نوع حصة',
        'default' => 'افتراضي',
    ],
    'leave_status' => [
        'pending' => 'بانتظار القرار',
        'approved' => 'معتمدة',
        'rejected' => 'مرفوضة',
        'cancelled' => 'ملغاة',
    ],
];
