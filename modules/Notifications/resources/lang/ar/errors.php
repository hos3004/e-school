<?php

declare(strict_types=1);

/*
| رسائل أخطاء قواعد العمل في موديول Notifications.
| تُستهلك عبر __('notifications::errors.key') — المفاتيح تصف المعنى لا الصياغة.
*/

return [

    'channel_disabled' => 'قناة «:channel» غير مفعّلة في إعدادات المنصة.',

    'not_cancellable' => 'لا يمكن إلغاء الرسالة في حالة «:status» — الإلغاء متاح قبل الإرسال فقط.',

    'not_retryable' => 'لا يمكن إعادة محاولة الرسالة في حالة «:status» — الإعادة متاحة للرسائل الفاشلة فقط.',

    'failure_not_retryable' => 'هذا الفشل دائم ولا يجوز للنظام إعادة محاولته آليًا؛ يمكن للإدارة إعادة الإرسال بعد إصلاح السبب.',

    'manual_retry_actor_required' => 'يلزم تحديد المستخدم الإداري الذي طلب إعادة الإرسال اليدوية.',

    'not_readable' => 'لا يمكن تعليم هذا السجل كمقروء؛ يجب أن يكون إشعارًا مسلّمًا داخل التطبيق ومملوكًا للمستخدم الحالي.',

    'not_dispatchable' => 'لا يمكن بدء إرسال رسالة في حالة «:status» — الإرسال يبدأ من حالة الانتظار فقط.',

    'already_claimed' => 'الرسالة محجوزة بواسطة مرسِل آخر الآن.',

    'attempt_not_recordable' => 'لا يمكن تسجيل محاولة تسليم لرسالة في حالة «:status».',

    'invalid_status_transition' => 'انتقال الحالة من «:from» إلى «:to» غير مسموح.',

    'category_unknown' => 'فئة الإشعار «:category» غير معروفة في إعدادات المنصة.',

    'event_id_required' => 'يلزم معرّف الحدث المصدر لإضافة الإشعار إلى قائمة الانتظار بأمان.',

    'gateway_unconfigured' => 'بوابة القناة «:channel» غير مهيأة — لا يوجد تنفيذ مسجّل لهذه القناة.',

    'gateway_channel_mismatch' => 'بوابة القناة لا تدعم «:actual»؛ القناة المتوقعة هي «:expected».',

    'template_missing' => 'لا يوجد قالب فعّال للحدث «:event» عبر قناة «:channel» باللغة «:locale».',

    'template_parameter_missing' => 'بارامتر القالب «:parameter» غير موجود في حمولة الحدث «:event».',

    'email_recipient_invalid' => 'لا يملك المستلم عنوان بريد إلكتروني صالحًا للتسليم.',

    'mail_transport_failed' => 'تعذّر الوصول مؤقتًا إلى ناقل البريد الإلكتروني.',
    'manual_retry_reason_required' => 'يلزم كتابة سبب واضح لإعادة الإرسال اليدوية.',
    'cancel_reason_required' => 'يلزم كتابة سبب واضح لإلغاء الرسالة.',
];
