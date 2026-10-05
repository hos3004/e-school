<?php

declare(strict_types=1);

/*
| رسائل الأخطاء في موديول Recordings.
| تُستهلك عبر __('recordings::errors.key') — المفاتيح تصف المعنى لا الصياغة.
*/

return [
    'invalid_transition' => 'لا يمكن نقل التسجيل من «:from» إلى «:to».',
    'duplicate_external_id' => 'يوجد تسجيل مسجَّل مسبقًا بنفس المعرّف الخارجي على :provider.',
    'archive_driver_missing' => 'لا يوجد سائق أرشفة مضبوط، ولا يمكن أرشفة التسجيل.',
    'already_deleted' => 'هذا التسجيل محذوف مسبقًا.',
    'delete_expired' => 'لا يمكن حذف تسجيل منتهٍ (الحالة: :status).',
    'deleter_required' => 'يجب تحديد المستخدم الذي نفّذ الحذف.',
    'deletion_reason_required' => 'حذف التسجيل يتطلب سببًا موثّقًا.',
    'not_watchable' => 'لا يمكن مشاهدة هذا التسجيل في حالته الحالية (:status).',
    'download_not_allowed' => 'تنزيل التسجيلات غير مسموح وفق السياسة.',
    'grant_target_invalid' => 'اختر مستفيدًا واحدًا فقط: مستخدمًا أو مجموعة.',
    'grant_reason_required' => 'منح الوصول إلى التسجيل يتطلب سببًا موثّقًا.',
    'grant_expiry_invalid' => 'يجب أن يكون انتهاء صلاحية الوصول إلى التسجيل في المستقبل.',
    'grant_status_invalid' => 'لا يمكن منح الوصول إلى تسجيل في حالته الحالية (:status).',
    'granter_required' => 'يجب أن يكون مانح الوصول مستخدمًا صالحًا داخل المؤسسة.',
    'grant_target_not_found' => 'المستفيد المحدد غير موجود داخل المؤسسة.',
    'grant_duplicate' => 'توجد منحة نشطة بالفعل لنفس المستفيد.',
    'revocation_context_required' => 'إلغاء المنحة يتطلب منفذًا وسببًا موثقًا.',
    'grant_not_found' => 'منحة الوصول غير موجودة لهذا التسجيل.',
    'grant_already_revoked' => 'منحة الوصول ملغاة مسبقًا.',
    'context_invalid' => 'الحصة أو الفصل الافتراضي لا ينتميان إلى المؤسسة المحددة.',
];
