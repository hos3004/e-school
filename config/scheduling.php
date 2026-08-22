<?php

declare(strict_types=1);

/**
 * قواعد الجدولة والإلغاء والتأجيل.
 *
 * كل رقم هنا سياسة مدرسية — يُقرأ من هذا الملف أو من إعدادات المؤسسة،
 * وممنوع نسخه داخل كود الموديولات. انظر docs/13-scheduling-rules.md
 *
 * مرجع القرار: إجابات العميل — الإلغاء قبل ساعة، التأجيل قبل ربع ساعة،
 * وما دون ذلك يُحتسب تغيّبًا.
 */
return [

    /*
     * مهل الإخطار قبل موعد الحصة (بالدقائق).
     */
    'notice' => [
        // الإلغاء: يجب الإخطار قبل الموعد بـ 60 دقيقة على الأقل.
        'cancellation_minutes' => env('SCHEDULING_CANCEL_NOTICE', 60),

        // التأجيل: يجب تقديم الطلب قبل الموعد بـ 15 دقيقة على الأقل.
        'postponement_minutes' => env('SCHEDULING_POSTPONE_NOTICE', 15),

        // ما دون هاتين المهلتين يُسجَّل تلقائيًا كـ no_show (تغيّب بدون عذر).
        'below_notice_outcome' => 'no_show',
    ],

    /*
     * دورة التأجيل.
     *
     * الطالب يطلب تأجيلًا مقترحًا له موعد ← يصل إشعار للمعلم وللإدارة ←
     * المعلم يؤكد الموعد أو يرشّح بديلًا ← الموعد النهائي يحدده المعلم ←
     * تُنشأ حصة تلافي مرتبطة بالحصة الأصلية.
     */
    'postponement' => [
        'requires_teacher_approval' => true,
        'notify_admin_on_request' => true,

        // مهلة رد المعلم قبل تصعيد الطلب للإدارة (بالساعات).
        'teacher_response_sla_hours' => 12,

        // أقصى عدد تأجيلات لنفس الطالب في الشهر — 0 يعني بلا حد.
        'max_per_student_per_month' => env('SCHEDULING_MAX_POSTPONE_MONTH', 4),

        // حصة التلافي يجب أن تُعقد خلال هذه المدة من الموعد الأصلي.
        'makeup_window_days' => 30,
        'makeup_creates_linked_session' => true,
    ],

    /*
     * الإلغاء.
     *
     * قرار العميل: الإلغاء لا يقابله حصة تلافي إطلاقًا.
     */
    'cancellation' => [
        'creates_makeup' => false,
        'requires_reason' => true,

        // في مرحلة الدفع: إلغاء متأخر يستوجب غرامة (خلف feature flag).
        'late_penalty_enabled' => env('FEATURE_STUDENT_BILLING', false),
    ],

    /*
     * منع التعارضات — تُفحص قبل الحفظ وتُفرض على مستوى قاعدة البيانات
     * بقيد EXCLUDE على مدى الوقت.
     */
    'conflicts' => [
        'teacher_double_booking' => 'block',      // معلم في حصتين متزامنتين
        'student_double_booking' => 'block',      // طالب في حصتين متزامنتين
        'group_double_booking' => 'block',
        'outside_teacher_availability' => 'warn', // خارج إتاحة المعلم المعلنة
        'on_holiday' => 'warn',                   // ضمن إجازة التقويم الأكاديمي

        // فاصل إلزامي بين حصتين متتاليتين لنفس المعلم (بالدقائق).
        'teacher_buffer_minutes' => 0,
    ],

    /*
     * أطوال الحصص المتاحة (بالدقائق).
     * 75 دقيقة هو الطول الحالي لحصص القرآن الجماعية.
     */
    'session_durations' => [30, 45, 60, 75, 90, 120],
    'default_duration_minutes' => 60,

    /*
     * الجدولة المتكررة.
     */
    'recurrence' => [
        // نستخدم RRULE (RFC 5545) لتخزين التكرار — نفس ما يفهمه FullCalendar.
        'max_horizon_weeks' => 26,

        // توليد الحصص الفعلية من قاعدة التكرار قبل الموعد بهذه المدة.
        'materialize_ahead_days' => 60,
        'skip_holidays' => true,
    ],

    /*
     * نافذة إغلاق الحصة تلقائيًا بعد انتهاء وقتها المجدول (بالدقائق).
     * بعدها تُرصد النتائج وتُنشأ قيود الرواتب.
     */
    'auto_finalize_after_minutes' => 30,

    /*
     * التذكيرات قبل الحصة (بالدقائق قبل البداية).
     */
    'reminders' => [
        'student' => [1440, 60, 10],
        'teacher' => [1440, 30],
        'guardian' => [1440],
    ],
];
