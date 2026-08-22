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
     * من يرى التسجيل — **الرؤية الافتراضية مغلقة**.
     *
     * تحديث العميل 2026-08-22 (docs/client-answers.md §CLIENT UPDATE §ف):
     * الطالب وولي الأمر لا يريان التسجيل تلقائيًا مهما كانت علاقتهما بالحصة.
     * الإتاحة تتم يدويًا بمنحة صريحة (recording_access_grants) لها انتهاء
     * وقابلة للإلغاء. القيم القديمة (student_enrolled/guardian/other_students
     * = true) بطلت.
     *
     * الروابط موقّعة ومؤقتة دائمًا — لا رابط عام أبدًا، ومعرفة الـURL ليست صلاحية.
     */
    'access' => [
        // الافتراضي: ممنوع. المنحة اليدوية هي الطريق الوحيد للطالب والمجموعة.
        'student_enrolled' => false,
        'guardian_of_student' => false,
        'other_students_in_group' => false,

        // المعلم يرى تسجيل حصته هو فقط — ولا يُنزّله افتراضيًا.
        'teacher_of_session' => true,
        'teacher_can_download' => env('RECORDING_ALLOW_TEACHER_DOWNLOAD', false),

        // المشرف يحتاج صلاحية صريحة؛ وجوده مشرفًا لا يكفي.
        'supervisor' => 'requires_permission',
        'supervisor_permission' => 'recording.view.any',

        'admin' => true,

        'signed_url_ttl_minutes' => 120,
        'allow_download' => env('RECORDING_ALLOW_STUDENT_DOWNLOAD', false),

        // الطالب الموقوف أو المجمّد لا يصل للتسجيلات حتى لو مُنح وصولًا.
        'blocked_for_frozen_enrollment' => true,
    ],

    /**
     * منح الوصول اليدوية — الطريق الوحيد لإتاحة تسجيل لطالب أو مجموعة.
     */
    'grants' => [
        'enabled' => true,
        'granter_permission' => 'recording.grant',
        'revoker_permission' => 'recording.grant',

        // انتهاء افتراضي للمنحة (بالأيام) — 0 يعني بلا انتهاء ما لم يُحدَّد.
        'default_expires_days' => (int) env('RECORDING_GRANT_DEFAULT_DAYS', 30),

        // المنحة لا تتجاوز عمر التسجيل نفسه.
        'capped_by_recording_expiry' => true,
        'allow_download_by_default' => false,
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
