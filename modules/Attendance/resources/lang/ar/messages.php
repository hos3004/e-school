<?php

declare(strict_types=1);

/*
| رسائل موديول Attendance العامة.
| تُستهلك عبر __('attendance::messages.key') — ولا نص ظاهر خارج ملفات الترجمة.
*/

return [
    'pending_confirmation' => 'بانتظار الاعتماد',
    'record_reason' => 'رصد آلي من بيانات دخول وخروج الفصل.',
    'confirm_reason' => 'اعتماد حالة الحضور المشتقة بعد المراجعة.',
    'system_actor' => 'النظام',
    'not_available' => 'غير متاح',
    'demo_override_reason' => 'تصحيح إداري للحالة بعد مراجعة تسجيل الفصل.',
    'seeder_no_participants' => 'لا توجد مشاركات حصص بعد — شغّل بذر Sessions أولًا لتوليد قيود حضور تجريبية.',
];
