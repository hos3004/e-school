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

    'not_dispatchable' => 'لا يمكن بدء إرسال رسالة في حالة «:status» — الإرسال يبدأ من حالة الانتظار فقط.',

    'already_claimed' => 'الرسالة محجوزة بواسطة مرسِل آخر الآن.',

    'attempt_not_recordable' => 'لا يمكن تسجيل محاولة تسليم لرسالة في حالة «:status».',

    'invalid_status_transition' => 'انتقال الحالة من «:from» إلى «:to» غير مسموح.',

    'category_unknown' => 'فئة الإشعار «:category» غير معروفة في إعدادات المنصة.',

    'event_id_required' => 'يلزم معرّف الحدث المصدر لإضافة الإشعار إلى قائمة الانتظار بأمان.',

    'gateway_unconfigured' => 'بوابة القناة «:channel» غير مهيأة — لا يوجد تنفيذ مسجّل لهذه القناة.',

    'gateway_channel_mismatch' => 'بوابة القناة لا تدعم «:actual»؛ القناة المتوقعة هي «:expected».',
];
