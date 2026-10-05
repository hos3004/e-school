<?php

declare(strict_types=1);

/*
| من أين تأتي سجلات الصفحات التي لا تُنشأ من اللوحة — reporting::origin
|
| الجدول الفارغ بلا تفسير يقرأ كعطل. هذه النصوص تقول للمستخدم أين يقع الإجراء
| فعلًا بدل أن تتركه يبحث عن زرٍّ لا وجود له عمدًا.
*/

return [
    'event_log' => [
        'heading' => 'No report events yet',
        'description' => 'Every domain event projected onto read dashboards is logged here. Use it to diagnose a dashboard number that disagrees with its source.',
        'action' => '',
    ],
    'student_dashboard' => [
        'heading' => 'No student dashboards yet',
        'description' => 'Built automatically from attendance, assessment and discipline events. Never created by hand; corrected when it drifts from its source.',
        'action' => 'Open students',
    ],
    'teacher_dashboard' => [
        'heading' => 'No teacher dashboards yet',
        'description' => 'Built from session and report events. Appears after a teacher\'s first approved session.',
        'action' => 'Open staff profiles',
    ],
    'snapshot' => [
        'heading' => 'No organization snapshots yet',
        'description' => 'Snapshots freeze the organization\'s numbers at a point in time; one can also be captured now on demand.',
        'action' => '',
    ],
];
