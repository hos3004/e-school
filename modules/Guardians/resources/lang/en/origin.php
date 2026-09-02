<?php

declare(strict_types=1);

/*
| من أين تأتي سجلات الصفحات التي لا تُنشأ من اللوحة — guardians::origin
|
| الجدول الفارغ بلا تفسير يقرأ كعطل. هذه النصوص تقول للمستخدم أين يقع الإجراء
| فعلًا بدل أن تتركه يبحث عن زرٍّ لا وجود له عمدًا.
*/

return [
    'link' => [
        'heading' => 'No guardian links yet',
        'description' => 'A link is created from the guardian profile by attaching a student; it is then verified, with one primary guardian per student.',
        'action' => 'Open guardian profiles',
    ],
];
