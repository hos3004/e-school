<?php

declare(strict_types=1);

/*
| من أين تأتي سجلات الصفحات التي لا تُنشأ من اللوحة — audit::origin
|
| الجدول الفارغ بلا تفسير يقرأ كعطل. هذه النصوص تقول للمستخدم أين يقع الإجراء
| فعلًا بدل أن تتركه يبحث عن زرٍّ لا وجود له عمدًا.
*/

return [
    'heading' => 'No audit entries yet',
    'description' => 'An entry is written automatically for every change to attendance, academic status, money, permissions or recordings. The ledger is append-only.',
    'action' => '',
];
