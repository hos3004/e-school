<?php

declare(strict_types=1);

/*
| رسائل موديول VirtualClassroom العامة.
| تُستهلك عبر __('virtualclassroom::messages.key') — ولا نص ظاهر خارج ملفات الترجمة.
*/

return [
    'smoke_config' => 'المزوّد: :provider | العنوان: :base_url | بصمة السر: :fingerprint',
    'smoke_reason' => 'سبب الخطأ: :reason',
    'smoke_health_ok' => 'فحص صحة مزوّد الفصل نجح.',
    'smoke_health_failed' => 'فشل فحص صحة مزوّد الفصل: :reason',
    'smoke_created' => 'تم إنشاء الفصل التجريبي :meeting.',
    'smoke_ended' => 'تم إنهاء الفصل التجريبي :meeting.',
    'smoke_running' => 'الفصل يعمل الآن.',
    'smoke_not_running' => 'الفصل لا يعمل الآن.',
    'smoke_participants' => 'عدد المشاركين الحاليين: :count.',
    'smoke_recordings' => 'عدد التسجيلات المتاحة: :count.',
    'smoke_join_moderator' => 'رابط دخول المعلّم:',
    'smoke_join_viewer' => 'رابط دخول الطالب:',
    'smoke_default_title' => 'فصل اختبار المنصة',
    'smoke_default_name' => 'مستخدم اختبار',
    'smoke_meeting_required' => 'يلزم تمرير خيار --meeting لهذا الإجراء.',
    'smoke_unknown_action' => 'إجراء الفحص غير معروف: :action.',
    'webhook_unsupported' => 'المزوّد :provider لا يدعم تسجيل webhook برمجيًا.',
    'webhook_count' => 'عدد اشتراكات webhook: :count.',
    'webhook_row' => ':hook | :callback | نطاق الحصة: :meeting',
    'webhook_scope_global' => 'كل الحصص',
    'webhook_registered' => 'تم تسجيل webhook :hook عند :callback.',
    'webhook_removed' => 'تم حذف اشتراك webhook :hook.',
    'webhook_hook_required' => 'يلزم تمرير خيار --hook لحذف الاشتراك.',
    'default_participant_name' => 'مشارك',
    'default_classroom_title' => 'حصة مباشرة',
    'recordings_synced' => 'تمت مزامنة :count تسجيلات جاهزة.',
    'provision_reason' => 'إنشاء الفصل المباشر قبل موعد الحصة.',
    'portal_provision_reason' => 'تأكد بوابة المستخدم من جاهزية الفصل عند طلب الدخول.',
    'webhook_started_reason' => 'أبلغ المزوّد أن الفصل بدأ فعليًا.',
    'webhook_ended_reason' => 'أبلغ المزوّد أن الفصل انتهى فعليًا.',
    'scheduled_provision_reason' => 'تجهيز آلي للفصل قبل موعد الحصة.',
    'provisioning_summary' => 'الفصول الجاهزة: :provisioned، والفاشلة: :failed.',
];
