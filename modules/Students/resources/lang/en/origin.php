<?php

declare(strict_types=1);

/*
| من أين تأتي سجلات الصفحات التي لا تُنشأ من اللوحة — students::origin
|
| الجدول الفارغ بلا تفسير يقرأ كعطل. هذه النصوص تقول للمستخدم أين يقع الإجراء
| فعلًا بدل أن تتركه يبحث عن زرٍّ لا وجود له عمدًا.
*/

return [
    'application' => [
        'heading' => 'No registration applications yet',
        'description' => 'Applications arrive from the public registration form. Copy the form link, publish it in your advert, then review and place applicants here.',
        'action' => 'Open registration forms',
    ],
];
