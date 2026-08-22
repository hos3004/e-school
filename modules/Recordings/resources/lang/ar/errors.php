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
];
