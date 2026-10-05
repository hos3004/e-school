<?php

declare(strict_types=1);

/*
| من أين تأتي سجلات الصفحات التي لا تُنشأ من اللوحة — discipline::origin
|
| الجدول الفارغ بلا تفسير يقرأ كعطل. هذه النصوص تقول للمستخدم أين يقع الإجراء
| فعلًا بدل أن تتركه يبحث عن زرٍّ لا وجود له عمدًا.
*/

return [
    'action' => [
        'heading' => 'No discipline actions yet',
        'description' => 'The escalation ladder creates these automatically when violations reach the configured threshold — they are never added by hand.',
        'action' => 'Open violations',
    ],
    'reactivation' => [
        'heading' => 'No reactivation requests',
        'description' => 'A frozen student submits the request from their portal; it appears here to approve or reject with a mandatory decision note.',
        'action' => '',
    ],
    'violation' => [
        'heading' => 'No violations recorded',
        'description' => 'Violations are raised automatically from unexcused absence, or logged by a supervisor. They are never deleted — waiving is the only way to void one.',
        'action' => 'Open attendance',
    ],
];
