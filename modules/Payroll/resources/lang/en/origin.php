<?php

declare(strict_types=1);

/*
| من أين تأتي سجلات الصفحات التي لا تُنشأ من اللوحة — payroll::origin
|
| الجدول الفارغ بلا تفسير يقرأ كعطل. هذه النصوص تقول للمستخدم أين يقع الإجراء
| فعلًا بدل أن تتركه يبحث عن زرٍّ لا وجود له عمدًا.
*/

return [
    'entry' => [
        'heading' => 'No payroll entries yet',
        'description' => 'An entry is created automatically when a session is approved, at the teacher rate of that moment. The ledger is append-only — corrections are new adjustment entries.',
        'action' => 'Open sessions',
    ],
    'period' => [
        'heading' => 'No payroll periods yet',
        'description' => 'A period opens automatically with the first entry of the month. Once paid it locks, and later changes land in the next period.',
        'action' => '',
    ],
];
