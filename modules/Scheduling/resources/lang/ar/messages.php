<?php

declare(strict_types=1);

/*
| رسائل موديول Scheduling العامة.
| تُستهلك عبر __('scheduling::messages.key') — ولا نص ظاهر خارج ملفات الترجمة.
*/

return [
    'generated_from_schedule' => 'توليد آلي من قالب الجدول.',
    'postponement_requested' => 'تم إرسال طلب التأجيل للمراجعة.',
    'teacher_approved_postponement' => 'اعتمد المعلم الموعد المقترح.',
    'postponement_approved' => 'تم اعتماد موعد التأجيل.',
    'postponement_alternative_proposed' => 'تم إرسال الموعد البديل.',
    'student_approved_postponement' => 'وافق الطالب على الموعد البديل الذي اقترحه المعلم.',
    'postponement_rejected' => 'تم رفض طلب التأجيل.',
    'schedule_change_requested' => 'أُرسل طلب تغيير الموعد الدائم إلى طلاب الكورس، ولن يسري قبل قبولهم جميعًا.',
    'schedule_change_accepted' => 'سُجّل قبولك للموعد الجديد.',
    'schedule_change_declined' => 'سُجّل رفضك، والموعد الحالي مستمر كما هو.',
    'schedule_change_withdrawn' => 'تم سحب طلب تغيير الموعد الدائم.',
];
