<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول VirtualClassroom.
| تُستهلك عبر __('virtualclassroom::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [
    'provider_configuration' => 'إعداد مزوّد الفصل الافتراضي غير مكتمل أو غير صحيح.',
    'provider_unavailable' => 'خدمة الفصل الافتراضي غير متاحة مؤقتًا. يُرجى المحاولة بعد قليل.',
    'provider_rejected' => 'رفض مزوّد الفصل الافتراضي العملية المطلوبة.',
    'unsupported_capability' => 'مزوّد الفصل الافتراضي المحدد لا يدعم :capability.',
    'capability_runtime_recording_control' => 'بدء التسجيل أو إيقافه بعد بداية الحصة',
    'invalid_webhook_signature' => 'توقيع إشعار الفصل الافتراضي غير صالح.',
];
