<?php

declare(strict_types=1);

/*
| تسميات أعمدة الجدول وحقول النموذج في لوحة Filament.
| تُستهلك عبر __('attendance::fields.key').
*/

return [
    'session_participant_id' => 'مشاركة الحصة',
    'attended_minutes' => 'دقائق الحضور',
    'joined_after_minutes' => 'دقائق التأخر',
    'left_before_minutes' => 'دقائق الانصراف المبكر',
    'status' => 'الحالة النهائية',
    'derived_status' => 'الحالة المشتقة آليًا',
    'new_status' => 'الحالة الجديدة',
    'confirmed_at' => 'وقت الاعتماد',
    'override_reason' => 'سبب التجاوز',
    'reason' => 'السبب',
    'student' => 'الطالب',
    'student_code' => 'رقم الطالب',
    'session' => 'الحصة',
    'course' => 'الكورس',
    'group' => 'المجموعة',
    'teacher' => 'المعلم',
    'session_status' => 'حالة الحصة',
    'scheduled_start' => 'موعد البداية',
    'scheduled_end' => 'موعد النهاية',
    'first_joined_at' => 'أول دخول',
    'last_left_at' => 'آخر خروج',
    'classroom_minutes' => 'دقائق الفصل المسجلة',
    'action' => 'العملية',
    'actor' => 'المنفذ',
    'changed_at' => 'وقت التغيير',
];
