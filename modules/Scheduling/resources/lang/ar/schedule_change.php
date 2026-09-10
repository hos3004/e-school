<?php

declare(strict_types=1);

/*
| نصوص طلب تغيير الموعد الدائم — حالات الطلب وردود الطلاب وصياغة الموعد.
| تُستهلك عبر __('scheduling::schedule_change.key').
*/

return [
    'pending' => 'بانتظار قبول كل الطلاب',
    'applied' => 'طُبّق الموعد الجديد',
    'rejected' => 'رفضه أحد الطلاب',
    'withdrawn' => 'سحبه المعلم',
    'expired' => 'انقضت مهلة رد الطلاب',

    'approval_pending' => 'بانتظار ردك',
    'approval_accepted' => 'قبل الموعد الجديد',
    'approval_rejected' => 'رفض الموعد الجديد',

    'slot' => ':day الساعة :time',
    'slot_separator' => 'و',
];
