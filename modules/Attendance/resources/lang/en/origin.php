<?php

declare(strict_types=1);

/*
| من أين تأتي سجلات الصفحات التي لا تُنشأ من اللوحة — attendance::origin
|
| الجدول الفارغ بلا تفسير يقرأ كعطل. هذه النصوص تقول للمستخدم أين يقع الإجراء
| فعلًا بدل أن تتركه يبحث عن زرٍّ لا وجود له عمدًا.
*/

return [
    'heading' => 'No attendance records yet',
    'description' => 'Attendance is recorded during or after a session from the session page. This page is for reviewing, confirming and overriding it.',
    'action' => 'Open sessions',
];
