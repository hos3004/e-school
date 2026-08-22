<?php

declare(strict_types=1);

/**
 * التسجيلات: الاحتفاظ والوصول والأرشفة.
 *
 * قرار العميل: كل الحصص تُسجَّل، والاحتفاظ 30 يومًا، والأرشيف على درايف
 * بسعة 5 تيرابايت. أكثر من نصف الطلاب قُصّر — لذلك الخصوصية ليست اختيارية.
 */
return [

    'retention_days' => env('RECORDING_RETENTION_DAYS', 30),

    /**
     * ماذا يحدث بعد انتهاء مدة الاحتفاظ.
     * archive_then_delete = يُنقل للأرشيف البارد ثم يُحذف من التخزين الساخن.
     */
    'on_expiry' => env('RECORDING_ON_EXPIRY', 'archive_then_delete'),

    'storage' => [
        // التخزين الساخن أثناء مدة الاحتفاظ.
        'hot_disk' => env('FILESYSTEM_DISK', 'r2'),
        // الأرشيف البارد: google_drive أو r2_cold أو none.
        'archive_driver' => env('RECORDING_ARCHIVE_DRIVER', 'google_drive'),
        'archive_folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
        // مسار منظّم داخل الأرشيف يسهّل الرجوع اليدوي.
        'archive_path_pattern' => '{year}/{month}/{program}/{group}/{session_date}-{session_id}',
    ],

    /**
     * من يرى التسجيل.
     * الروابط موقّعة ومؤقتة دائمًا — لا رابط عام أبدًا.
     */
    'access' => [
        'student_enrolled' => true,      // الطالب المقيَّد في الحصة فقط
        'guardian_of_student' => true,
        'teacher_of_session' => true,
        'supervisor' => true,
        'admin' => true,
        'other_students_in_group' => true,
        'signed_url_ttl_minutes' => 120,
        'allow_download' => env('RECORDING_ALLOW_STUDENT_DOWNLOAD', false),
        // الطالب الموقوف أو المجمّد لا يصل للتسجيلات.
        'blocked_for_frozen_enrollment' => true,
    ],

    /**
     * الخصوصية والموافقة — وجود قُصّر يجعل هذا إلزاميًا.
     */
    'privacy' => [
        'require_consent_on_enrollment' => true,
        'guardian_consent_required_under_age' => 18,
        'show_in_class_recording_indicator' => true,
        // طلب حذف تسجيل بعينه (حق الاعتراض) — يمر باعتماد الإدارة.
        'allow_deletion_request' => true,
        'deletion_approver_permission' => 'recording.delete',
        // كل مشاهدة وتنزيل تُسجَّل في سجل التدقيق.
        'log_every_view' => true,
    ],

    /**
     * المعالجة بعد انتهاء الحصة.
     */
    'processing' => [
        'poll_provider_interval_minutes' => 10,
        'max_wait_hours' => 12,
        'generate_thumbnail' => true,
        'notify_on_ready' => ['teacher', 'supervisor'],
    ],
];
