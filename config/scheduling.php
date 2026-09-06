<?php

declare(strict_types=1);

/**
 * قواعد الجدولة والإلغاء والتأجيل.
 *
 * كل رقم هنا سياسة مدرسية — يُقرأ من هذا الملف أو من إعدادات المؤسسة،
 * وممنوع نسخه داخل كود الموديولات. انظر docs/13-scheduling-rules.md
 *
 * مرجع القرار: إجابات العميل — الإلغاء والتأجيل قبل ساعة،
 * وما دون ذلك يُحتسب تغيّبًا.
 */
return [

    /*
     * مهل الإخطار قبل موعد الحصة (بالدقائق).
     */
    'notice' => [
        // الإلغاء: يجب الإخطار قبل الموعد بـ 60 دقيقة على الأقل.
        'cancellation_minutes' => env('SCHEDULING_CANCEL_NOTICE', 60),

        // التأجيل: يجب تقديم الطلب قبل الموعد بـ 60 دقيقة على الأقل.
        'postponement_minutes' => env('SCHEDULING_POSTPONE_NOTICE', 60),

        // ما دون هاتين المهلتين يُسجَّل تلقائيًا كـ no_show (تغيّب بدون عذر).
        'below_notice_outcome' => 'no_show',
    ],

    /*
     * اعتذار الطالب يخضع لنفس مهلة الساعة. في الجماعي يُعذر المشارك فقط؛
     * وفي الفردي تصبح الحصة معتذرًا عنها.
     */
    'student_apology' => [
        'min_notice_minutes' => (int) env('STUDENT_APOLOGY_MIN_NOTICE_MINUTES', 60),
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
    'individual_session_durations' => [25, 35, 55],
    'default_duration_minutes' => 60,
    'default_individual_duration_minutes' => 35,

    'booking_slots' => [
        'interval_minutes' => 5,
        'day_start' => '00:00',
        'day_end' => '24:00',
        'preview_limit' => 8,
    ],

    'individual_quran' => [
        'course_code' => 'C-QURAN-IND',
        'bulk_max_students' => 50,
        'max_interval_weeks' => 12,
        'reason_max_length' => 1000,
        // نافذة العرض الافتراضية في صفحة التسكين؛ لا تغيّر إتاحة المعلم الفعلية.
        'selection_window_start' => '06:00',
        'selection_window_end' => '23:00',
    ],

    /*
     * الجدولة المتكررة.
     */
    'recurrence' => [
        // نستخدم RRULE (RFC 5545) لتخزين التكرار — نفس ما يفهمه FullCalendar.
        'max_horizon_weeks' => 26,

        // توليد الحصص الفعلية من قاعدة التكرار قبل الموعد بهذه المدة.
        'materialize_ahead_days' => 60,
        'skip_holidays' => true,

        // الأحداث داخل هذه النافذة لا تتغير عند تعديل القالب.
        'edit_lock_hours' => 48,
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
        'teacher' => [1440, 60, 30],
        'guardian' => [1440],
    ],

    /*
     * عامل إرسال تذكير البريد قبل الحصة. يحتفظ كل صف حصة بعلامة الإرسال
     * لضمان عدم تكرار التذكير عند تشغيل المجدول كل دقيقة.
     */
    'reminder_dispatch' => [
        'before_minutes' => 60,
        'batch_size' => 200,
    ],

    'notification_summary' => [
        'max_sessions' => 200,
    ],

    /*
     * اعتذار المعلم عن الحصة.
     *
     * الاعتذار **لا يُلغي الحصة** ويُعتمد فورًا دون موافقة إدارية.
     * يبدأ البحث عن بديل، ويظل المعلم الأصلي مسجَّلًا في الحصة.
     * سُلَّم المتابعة والنافذة المتحركة في config/discipline.php ('teacher').
     */
    'apology' => [
        'requires_reason' => true,
        'requires_approval' => false,
        'approver_permission' => 'session.apology.approve',

        // الاعتذار يجب أن يصل قبل الحصة بساعة على الأقل.
        'min_notice_minutes' => (int) env('APOLOGY_MIN_NOTICE_MINUTES', 60),

        // مهلة رد المشرف قبل تصعيد الطلب (بالساعات).
        'approver_sla_hours' => 6,

        // ما يحدث بعد الاعتماد — بهذا الترتيب.
        'on_approval' => [
            'cancels_session' => false,
            'opens_substitute_search' => true,
            'notifies_supervisor' => true,

            // لم يُوجد بديل قبل هذه المهلة من بداية الحصة (بالدقائق) ->
            // تصعيد للإدارة. لا إلغاء آلي.
            'escalate_if_unfilled_minutes_before' => 60,
        ],
    ],

    /*
     * ترشيح المعلم البديل — الشروط التي تُفحص قبل العرض.
     *
     * كل شرط: block = لا يُعرض المرشح إلا بتجاوز إداري بسبب مكتوب.
     *          rank  = يُعرض لكنه يتأخر في الترتيب.
     */
    'substitute' => [
        'criteria' => [
            'qualified_for_course' => 'block',
            'active_status' => 'block',
            'same_organization' => 'block',
            'no_schedule_conflict' => 'block',
            'program_gender_rule' => 'block',
            'program_allows_teacher' => 'block',
            'not_on_leave' => 'block',
            'within_declared_availability' => 'rank',
        ],

        'override_permission' => 'session.substitute.override',
        'override_requires_reason' => true,

        // المعلم الأصلي لا يُمحى أبدًا؛ يبقى original_teacher_id كما هو.
        'preserves_original_teacher' => true,
        'max_candidates_returned' => 20,
    ],

    /*
     * تقرير الحصة.
     *
     * قاعدة العميل: المعلم يكتب التقرير خلال 60 دقيقة بعد نهاية الحصة،
     * وبعدها يصبح التقرير late. لا عقوبة آلية — تنبيه ومؤشر فقط.
     */
    'session_report' => [
        'deadline_minutes' => (int) env('SESSION_REPORT_DEADLINE_MINUTES', 60),

        // تذكير المعلم قبل انتهاء المهلة (بالدقائق قبل انقضائها).
        'reminder_before_deadline_minutes' => 15,
        'notify_on_late' => true,
        'late_notifies' => ['teacher', 'supervisor'],

        // لا يُغلق الشهر ولا يُمنع أي شيء بسبب تأخر التقرير في هذه المرحلة.
        'blocks_session_finalization' => false,
    ],

    /*
     * التزام المعلم بمدة الحصة — مؤشرات مراقبة لا عقوبات.
     *
     * تُحسب من أحداث الدخول والخروج القادمة من مزوّد الفصل المباشر.
     */
    'teacher_duration_compliance' => [
        'enabled' => true,

        // تسامح قبل احتساب تأخر أو خروج مبكر (بالدقائق).
        'late_tolerance_minutes' => (int) env('TEACHER_LATE_TOLERANCE_MINUTES', 5),
        'early_leave_tolerance_minutes' => (int) env('TEACHER_EARLY_LEAVE_TOLERANCE_MINUTES', 5),

        // ممنوع تحويل هذه المؤشرات إلى عقوبة آلية — المرحلة 1.5 على الأكثر.
        'automatic_sanctions' => false,
    ],

    /*
     * إتاحة المعلم والطالب — أساس الجدولة والمطابقة ومنع التعارض.
     */
    'availability' => [
        // هل تحتاج إتاحة المعلم اعتمادًا من المنصة قبل أن تصبح فعّالة؟
        'teacher_requires_approval' => env('AVAILABILITY_TEACHER_APPROVAL', false),
        'teacher_approver_permission' => 'staff.availability.approve',

        // إتاحة الطالب لا تحتاج اعتمادًا — تُستخدم في الترشيح فقط.
        'student_requires_approval' => false,

        // أقل طول لخانة إتاحة صالحة (بالدقائق).
        'min_slot_minutes' => 30,

        // أقصى مدى مستقبلي تُحسب فيه الخانات المتوافقة (بالأيام).
        'compatibility_horizon_days' => 60,

        // الجدولة خارج الإتاحة المعلنة: warn (تحذير للإدارة) أو block.
        'outside_declared' => env('AVAILABILITY_OUTSIDE_DECLARED', 'warn'),

        // الحصة الفردية لا تُحجز إلا داخل إتاحة معتمدة للمعلم.
        'individual_requires_declared' => true,
    ],

    'admin_hub' => [
        'max_sessions' => 40,
        'max_history' => 30,
    ],
];
