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
];
