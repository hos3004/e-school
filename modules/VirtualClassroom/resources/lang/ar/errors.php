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
    'session_not_joinable' => 'لا يمكن دخول هذه الحصة في حالتها الحالية.',
    'join_window_closed' => 'الدخول إلى الحصة غير متاح في هذا الوقت.',
    'student_frozen_cannot_join' => 'لا يمكن للطالب ذي القيد المجمد دخول الفصل.',
    'session_not_found' => 'الحصة غير موجودة داخل هذه المؤسسة.',
    'not_provisioned' => 'لم يُنشأ الفصل لدى المزوّد بعد.',
    'classroom_not_ready' => 'الفصل غير جاهز للدخول. راجع حالة الربط أو أعد محاولة الإنشاء.',
    'reason_required' => 'يجب كتابة سبب العملية.',
    'invalid_status' => 'لا يمكن إنشاء الفصل من حالته الحالية: :status.',
];
