<?php

declare(strict_types=1);

/*
| من أين تأتي سجلات الصفحات التي لا تُنشأ من اللوحة — notifications::origin
|
| الجدول الفارغ بلا تفسير يقرأ كعطل. هذه النصوص تقول للمستخدم أين يقع الإجراء
| فعلًا بدل أن تتركه يبحث عن زرٍّ لا وجود له عمدًا.
*/

return [
    'outbox' => [
        'heading' => 'The outbox is empty',
        'description' => 'Messages are queued automatically by system events — a scheduled session, an accepted registration, a discipline escalation — or manually from the Send notification button above.',
        'action' => '',
    ],
    'category' => [
        'heading' => 'No category settings yet',
        'description' => 'Settings are seeded for every notification category defined in the system; you edit their channels rather than create them here.',
        'action' => 'Open notification templates',
    ],
];
