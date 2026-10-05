<?php

declare(strict_types=1);

/*
| الرسائل العامة في موديول Recordings.
| تُستهلك عبر __('recordings::messages.key') — لا نص ظاهر خارج ملفات الترجمة.
*/

return [
    'duration_minutes' => ':minutes دقيقة',
    'seeder_no_organization' => 'بذر التسجيلات: لا توجد مؤسسة — تم التخطي.',
    'seeder_no_sessions' => 'بذر التسجيلات: لا توجد حصص (يملكها موديول آخر) — تم التخطي.',
    'unavailable' => 'غير متاح',
    'system_actor' => 'النظام',
    'provider_ingestion_reason' => 'استلام التسجيل من مزوّد الفصل الافتراضي.',
    'processing_completed_reason' => 'اكتملت معالجة ملف التسجيل لدى المزوّد.',
    'retention_archive_reason' => 'أرشفة التسجيل وفق سياسة الاحتفاظ.',
    'retention_expiry_reason' => 'انتهاء مدة الاحتفاظ بالتسجيل.',
    'retention_summary' => 'تمت معالجة :count تسجيلات وفق سياسة الاحتفاظ.',
    'access_granted' => 'تم منح الوصول إلى التسجيل.',
    'access_revoked' => 'تم إلغاء الوصول إلى التسجيل.',
    'archived' => 'تمت أرشفة التسجيل.',
    'deleted' => 'تم تعليق التسجيل مع حفظ السبب.',
    'size_megabytes' => ':size م.ب',
    'size_gigabytes' => ':size ج.ب',
];
