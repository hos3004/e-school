<?php

declare(strict_types=1);

/*
| نصوص لوحة Filament لموديول Attendance — لا نص مباشر في المورد.
*/

return [
    'navigation_group' => 'الحضور والانضباط',

    'attendance' => [
        'label' => 'قيد حضور',
        'plural' => 'قيود الحضور',
    ],

    'pages' => [
        'list_title' => 'قيود الحضور',
        'view_title' => 'تفاصيل قيد الحضور',
    ],

    'actions' => [
        'confirm' => 'اعتماد',
        'confirm_description' => 'سيُختم هذا القيد بحالتك المشتقة آليًا ويصبح نهائيًا للتقارير والمستحقات. هل تريد المتابعة؟',
        'override' => 'تجاوز الحالة',
        'reason_helper' => 'السبب يُسجَّل في سجل التدقيق مع اسمك ووقت التغيير.',
    ],
];
