<?php

declare(strict_types=1);

/*
| حالات موديول Groups: حالة المجموعة، حالة الانتساب، ودور المعلم.
| تُستهلك عبر __('groups::status.group.active') وهكذا.
*/

return [
    'group' => [
        'planning' => 'قيد التخطيط',
        'active' => 'نشطة',
        'completed' => 'مُختمة',
    ],
    'membership' => [
        'active' => 'منتسب',
        'left' => 'غادر',
    ],
    'teacher_role' => [
        'lead' => 'معلم أساسي',
        'assistant' => 'معلم مساعد',
        'substitute' => 'معلم تلافي',
    ],
];
