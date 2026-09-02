<?php

declare(strict_types=1);

/*
| من أين تأتي سجلات الصفحات التي لا تُنشأ من اللوحة — recordings::origin
|
| الجدول الفارغ بلا تفسير يقرأ كعطل. هذه النصوص تقول للمستخدم أين يقع الإجراء
| فعلًا بدل أن تتركه يبحث عن زرٍّ لا وجود له عمدًا.
*/

return [
    'heading' => 'No recordings yet',
    'description' => 'Recordings arrive automatically from the virtual classroom after a session ends, synced every ten minutes and retained for thirty days.',
    'action' => 'Open sessions',
];
