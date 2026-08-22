<?php

declare(strict_types=1);

/*
| تسميات الحقول في موديول Recordings.
| تُستهلك عبر __('recordings::fields.key').
*/

return [
    'id' => 'المعرّف',
    'organization' => 'المؤسسة',
    'session' => 'الحصة',
    'classroom' => 'الفصل الافتراضي',
    'provider' => 'المزوّد',
    'external_recording_id' => 'معرّف التسجيل لدى المزوّد',
    'disk' => 'القرص',
    'path' => 'مسار الملف',
    'status' => 'الحالة',
    'duration' => 'المدة',
    'size' => 'الحجم (بايت)',
    'available_from' => 'متاح من',
    'expires_at' => 'ينتهي في',
    'deleted_at' => 'تاريخ الحذف',
    'deletion_reason' => 'سبب الحذف',
    'action' => 'نوع الوصول',
    'reason' => 'السبب',
];
