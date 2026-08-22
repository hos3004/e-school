<?php

declare(strict_types=1);

/*
| حقول موديول Reporting.
| تُستهلك عبر __('reporting::fields.key') في النماذج والموارد والطلبات.
*/

return [

    'id' => 'المعرّف',
    'organization' => 'المؤسسة',
    'enrollment' => 'التسجيل',
    'student' => 'الطالب',
    'staff' => 'المعلم',

    'sessions_total' => 'إجمالي الحصص',
    'sessions_attended' => 'الحصص المُحضَّرة',
    'sessions_missed' => 'الحصص الفائتة',
    'sessions_completed' => 'الحصص المكتملة',
    'attendance_rate' => 'نسبة الحضور',
    'violations_count' => 'عدد المخالفات',
    'freezes_count' => 'عدد مرات التجميد',

    'cancellations_by_self' => 'إلغاءات المعلم',
    'postponements' => 'مرات التأجيل',
    'payout' => 'المستحق الصافي',
    'last_session_at' => 'آخر حصة',
    'last_violation_at' => 'آخر مخالفة',

    'snapshot_date' => 'تاريخ اللقطة',
    'period_type' => 'نوع الفترة',
    'students_active' => 'الطلاب النشطون',
    'students_frozen' => 'الطلاب المجمّدون',
    'teachers_active' => 'المعلمون النشطون',
    'sessions_held' => 'الحصص المنعقدة',
    'sessions_cancelled' => 'الحصص الملغاة',

    'event_id' => 'معرّف الحدث',
    'event_name' => 'اسم الحدث',
    'event_module' => 'الموديول المصدر',
    'occurred_at' => 'وقت وقوع الحدث',
    'ingested_at' => 'وقت الإدخال',

    'column' => 'العمود المُصحَّح',
    'value' => 'القيمة الجديدة',
    'reason' => 'سبب التصحيح',

];
