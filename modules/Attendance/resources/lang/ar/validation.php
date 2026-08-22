<?php

declare(strict_types=1);

/*
| رسائل التحقق لطلبات موديول Attendance.
| تُستهلك من FormRequests عبر __('attendance::validation.key').
*/

return [
    'participant_required' => 'معرّف مشاركة الحصة مطلوب.',
    'participant_invalid' => 'معرّف مشاركة الحصة غير صالح.',
    'attended_minutes_required' => 'دقائق الحضور الفعلية مطلوبة.',
    'session_minutes_required' => 'المدة الكلية للحصة مطلوبة لحساب الحالة المشتقة.',
    'session_minutes_min' => 'مدة الحصة يجب أن تكون دقيقة واحدة على الأقل.',
    'minutes_integer' => 'الدقائق يجب أن تكون عددًا صحيحًا.',
    'minutes_min' => 'الدقائق يجب أن تكون صفرًا أو أكثر.',
    'minutes_max' => 'قيمة الدقائق أكبر من الحد المسموح.',
    'status_required' => 'الحالة الجديدة مطلوبة عند التجاوز.',
    'status_invalid' => 'الحالة المختارة غير معروفة.',
    'reason_required' => 'سبب التجاوز مطلوب وفق قاعدة التدقيق.',
    'reason_min' => 'سبب التجاوز قصير جدًا — اكتب سببًا مفهومًا.',
    'reason_max' => 'سبب التجاوز أطول من الحد المسموح.',
];
