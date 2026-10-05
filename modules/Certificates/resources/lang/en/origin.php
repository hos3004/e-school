<?php

declare(strict_types=1);

/*
| من أين تأتي سجلات الصفحات التي لا تُنشأ من اللوحة — certificates::origin
|
| الجدول الفارغ بلا تفسير يقرأ كعطل. هذه النصوص تقول للمستخدم أين يقع الإجراء
| فعلًا بدل أن تتركه يبحث عن زرٍّ لا وجود له عمدًا.
*/

return [
    'badge_award' => [
        'heading' => 'No badges awarded yet',
        'description' => 'Badges are granted automatically when their rule is met, or manually for a specific student via the award button.',
        'action' => '',
    ],
];
