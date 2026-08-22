<?php

declare(strict_types=1);

/*
| أسماء الحقول الظاهرة في رسائل التحقق ولوحة Filament.
| تُستهلك عبر __('attendance::attributes.key').
*/

return [
    'session_participant_id' => 'مشاركة الحصة',
    'attended_minutes' => 'دقائق الحضور الفعلية',
    'session_minutes' => 'مدة الحصة',
    'joined_after_minutes' => 'دقائق التأخر عن البداية',
    'left_before_minutes' => 'دقائق الانصراف قبل النهاية',
    'status' => 'حالة الحضور',
    'derived_status' => 'الحالة المشتقة آليًا',
    'confirmed_at' => 'وقت الاعتماد',
    'confirmed_by' => 'اعتمدها',
    'override_reason' => 'سبب التجاوز',
    'reason' => 'السبب',
];
