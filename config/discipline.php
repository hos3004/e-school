<?php

declare(strict_types=1);

/**
 * محرّك قواعد الانضباط.
 *
 * ممنوع منعًا باتًا كتابة شرط مثل (absences >= 3) داخل كود أي موديول.
 * المحرّك يقرأ القواعد من هنا (ومن جدول تجاوزات المؤسسة) ويطبّقها.
 *
 * مرجع القرار — إجابات العميل:
 *   غياب بدون عذر: أول مرة تنبيه · ثاني مرة تنبيه + تحذير بالتجميد ·
 *   ثالث مرة تجميد تلقائي. والعدّاد يُصفَّر كل شهر.
 *   فك التجميد لا يتم إلا باختبار وتقييم من الفريق الإداري.
 */
return [

    /*
     * نافذة احتساب المخالفات.
     *
     * rolling = آخر N يومًا من لحظة الاحتساب. هذا هو الصحيح بعد تحديث
     * العميل 2026-08-22: لا تصفير أول الشهر الميلادي.
     * القيمة القديمة 'monthly' بطلت — انظر docs/client-answers.md §CLIENT UPDATE §ل.
     */
    'counter_window' => env('DISCIPLINE_WINDOW', 'rolling'),
    'counter_window_days' => (int) env('DISCIPLINE_WINDOW_DAYS', 30),

    /*
     * ما الذي يُحتسب مخالفة أصلًا.
     */
    'countable_events' => [
        'no_show' => true,                // حجز الحصة ولم يحضر
        'unexcused_absence' => true,      // غياب بدون عذر
        'late_cancellation' => true,      // إلغاء بعد انقضاء المهلة
        'excused_absence' => false,       // غياب بعذر مقبول — لا يُحتسب
        'approved_postponement' => false, // تأجيل موافق عليه — لا يُحتسب
        'teacher_absence' => false,       // غياب المعلم لا يُحمَّل على الطالب
    ],

    /*
     * سُلَّم العقوبات — يُقرأ بالترتيب، وأعلى عتبة منطبقة هي التي تُطبَّق.
     *
     * threshold = عدد المخالفات داخل النافذة الذي يُشغّل الإجراء.
     */
    'ladder' => [
        [
            'threshold' => 1,
            'action' => 'notice',
            'severity' => 'info',
            'translation_key' => 'discipline.notice.first',
            'notify' => ['student', 'guardian'],
        ],
        [
            'threshold' => 2,
            'action' => 'warning',
            'severity' => 'warning',
            'translation_key' => 'discipline.notice.second',
            'notify' => ['student', 'guardian', 'supervisor'],

            // تحذير صريح بأن المخالفة القادمة تعني تجميدًا تلقائيًا.
            'warns_of_next' => 'auto_freeze',
        ],
        [
            'threshold' => 3,
            'action' => 'freeze_enrollment',
            'severity' => 'critical',
            'translation_key' => 'discipline.notice.third',
            'notify' => ['student', 'guardian', 'supervisor', 'admin'],
            'automatic' => true,
        ],
    ],

    /*
     * التجميد.
     *
     * قرار العميل: الحساب لا يُحذف أبدًا — يُمنع الوصول للكورسات فقط.
     */
    'freeze' => [
        'deletes_account' => false,
        'revokes_course_access' => true,
        'keeps_messaging_access' => true, // يظل يستطيع مراسلة الإدارة
        'keeps_history_visible' => true,
        'removes_from_future_sessions' => true,
    ],

    /*
     * فك التجميد — لا يتم آليًا بمرور الوقت.
     */
    'reactivation' => [
        'requires_request' => true,
        'requires_assessment' => true,

        // نموذج التقييم يُدار من موديول Assessments بنوع reactivation.
        'assessment_type' => 'reactivation',
        'approver_permission' => 'enrollment.reactivate',
        'passing_score_percent' => 60,
        'max_attempts' => 3,
        'cooldown_days_between_attempts' => 7,
    ],

    /*
     * التجميد الاختياري بطلب الطالب (إجازة مؤقتة).
     *
     * قرار العميل: الطالب يستطيع طلب تجميد مؤقت مع تحديد موعد العودة.
     */
    'voluntary_freeze' => [
        'requires_approval' => true,
        'approver_permission' => 'enrollment.pause',
        'requires_return_date' => true,
        'min_days' => 7,
        'max_days' => 90,
        'auto_resume_on_return_date' => true,

        // لا يُحتسب ضمن المخالفات ولا يؤثر على سجل الانضباط.
        'counts_as_violation' => false,
    ],

    /*
     * متابعة اعتذارات المعلم — مراقبة وتصعيد، لا عقاب آلي.
     *
     * قاعدة العميل 2026-08-22 (docs/client-answers.md §CLIENT UPDATE §ك):
     * لا فصل ولا إقصاء ولا تغيير حالة المعلم آليًا مهما تكرر الاعتذار.
     * كل ما يفعله النظام: تنبيه، تحذير، ثم إنشاء تصعيد للإدارة.
     * القرار النهائي يدوي دائمًا.
     */
    'teacher' => [
        // نافذة متحركة بالأيام — ليست شهرًا ميلاديًا ولا ربعًا.
        'counter_window' => 'rolling',
        'counter_window_days' => (int) env('TEACHER_APOLOGY_WINDOW_DAYS', 30),

        'countable_events' => [
            'apology' => true,              // اعتذار عن حصة تمت الموافقة عليه
            'teacher_absence' => true,
            'late_session_report' => true,
            'late_start' => true,
        ],

        /*
         * ما الذي يُحتسب في سُلَّم الاعتذارات تحديدًا.
         * الاعتذار المرفوض لا يُحتسب — لم يقع أثر تشغيلي.
         */
        'apology' => [
            'counts_only_when_approved' => true,
            'requires_reason' => true,
            'approver_permission' => 'session.apology.approve',

            // اعتذار المعلم لا يُلغي الحصة أبدًا — يبدأ البحث عن بديل.
            'cancels_session' => false,
            'triggers_substitute_search' => true,
        ],

        /*
         * سُلَّم التصعيد — الإجراءات إخطارية بحتة.
         * ممنوع إضافة suspend أو terminate هنا.
         */
        'ladder' => [
            [
                'threshold' => 1,
                'action' => 'record',
                'severity' => 'info',
                'notify' => [],
            ],
            [
                'threshold' => 2,
                'action' => 'warning',
                'severity' => 'warning',
                'translation_key' => 'discipline.teacher.apology.second',
                'notify' => ['teacher', 'admin'],
            ],
            [
                'threshold' => 3,
                'action' => 'escalation',
                'severity' => 'critical',
                'translation_key' => 'discipline.teacher.apology.third',
                'notify' => ['teacher', 'supervisor', 'admin'],
                'creates_escalation' => true,
            ],
        ],

        /*
         * أقفال أمان صريحة — لا يغيّرها أي كود.
         */
        'never_automatic' => [
            'suspend' => false,
            'terminate' => false,
            'change_status' => false,
            'remove_from_groups' => false,
        ],
    ],
];
