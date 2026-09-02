<?php

declare(strict_types=1);

/*
| من أين تأتي سجلات الصفحات التي لا تُنشأ من اللوحة — integrations::origin
|
| الجدول الفارغ بلا تفسير يقرأ كعطل. هذه النصوص تقول للمستخدم أين يقع الإجراء
| فعلًا بدل أن تتركه يبحث عن زرٍّ لا وجود له عمدًا.
*/

return [
    'delivery' => [
        'heading' => 'No webhook deliveries yet',
        'description' => 'A record is written for each delivery attempt to an external system. Dead deliveries can be requeued from the requeue button.',
        'action' => '',
    ],
];
