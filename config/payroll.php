<?php

declare(strict_types=1);

/**
 * قواعد احتساب مستحقات المعلمين والموظفين.
 *
 * مبدأ حاكم: المستحقات دفتر أستاذ (ledger) لا يُعدَّل.
 * عند إقفال الحصة تُنشأ قيدة بالسعر السائد وقتها ويُخزَّن السعر داخل القيدة.
 * تغيير سعر المعلم لاحقًا لا يمس قيدًا قديمًا أبدًا؛ التصحيح بقيدة تسوية جديدة.
 *
 * مرجع القرار: إجابات العميل عن الأسعار والخصومات.
 * التفصيل الكامل مع الأمثلة في docs/14-payroll-rules.md
 */
return [

    'currency' => env('ORG_DEFAULT_CURRENCY', 'EGP'),

    /*
     * أسس التعاقد المدعومة.
     *
     * per_session
     *     كل حصة مُقامة تولّد قيدة بسعر الحصة. مناسب للمعلم بالحصة.
     *
     * monthly_with_deductions
     *     المعلم له مبلغ شهري مقابل عدد حصص متفق عليه (مثال القرآن الجماعي:
     *     600 جنيه مقابل 12 حصة بواقع 75 دقيقة، بتقدير 50 جنيهًا للحصة).
     *     تُنشأ قيدة أساس شهرية، ثم قيدة خصم عن كل حصة لم تُقَم بسبب يخص المعلم.
     *     كشف الحساب يظهر: 600 أساس − 50 خصم حصة ملغاة = 550.
     *
     * course_fixed
     *     مبلغ ثابت للدورة كاملة (مثال: دورة البرمجة — عدد حصص/أيام/ساعات
     *     بمبلغ واحد محدد). يُوزَّع على الحصص حسب release أدناه.
     *
     * salaried
     *     راتب شهري ثابت مقابل مهام إدارية وتدريبية محددة في العقد.
     *     يُدرَج في سجل الالتزامات، وتُعرض نسبة الإنجاز، ويقبل الخصم والمكافأة.
     */
    'bases' => ['per_session', 'monthly_with_deductions', 'course_fixed', 'salaried'],
    'default_basis' => 'per_session',

    /*
     * توزيع مبلغ الدورة الثابتة على الفترات.
     * per_session   = المبلغ ÷ عدد الحصص المخطط لها، عند إقامة كل حصة.
     * on_completion = المبلغ كاملًا عند اكتمال الدورة.
     */
    'course_fixed_release' => 'per_session',

    /*
     * ترتيب استنباط سعر الحصة — أول مصدر يعطي قيمة هو المعتمد.
     *
     * التنوّع مقصود: برامج متعددة ومعلمون بكفاءات مختلفة، وسعر الفردي
     * يختلف عن الجماعي، وقد يختلف من برنامج لآخر.
     */
    'rate_resolution' => [
        'session_override',   // سعر استثنائي مثبّت على الحصة نفسها
        'contract_course',    // سعر المعلم في هذه المادة تحديدًا
        'contract_program',   // سعر المعلم في هذا البرنامج
        'contract_type',      // سعر المعلم حسب نوع الحصة (فردي/جماعي)
        'contract_default',   // السعر الافتراضي في عقد المعلم
        'program_default',    // السعر الافتراضي للبرنامج
    ],

    /*
     * ══════════════════════════════════════════════════════════════════════
     * مصفوفة النتائج — قلب النظام المالي.
     *
     * لكل نتيجة حصة: ماذا يحدث للمعلم، وماذا يحدث للطالب.
     *
     * teacher:
     *   full      = يُحتسب سعر الحصة كاملًا
     *   none      = لا يُحتسب شيء
     *   deduct    = خصم بقيمة سعر حصة (في أساس monthly_with_deductions)
     *   deferred  = يُؤجَّل حتى تُقام حصة التلافي
     *
     * student (لا يُطبَّق إلا عند تفعيل فوترة الطلاب):
     *   consume   = تُخصم حصة من رصيده
     *   keep      = لا تُخصم
     *   refund    = تُعاد له قيمة الحصة
     * ══════════════════════════════════════════════════════════════════════
     */
    'outcomes' => [

        // الحصة أُقيمت وحضر الطرفان.
        'completed' => [
            'teacher' => 'full',
            'student' => 'consume',
        ],

        // الطالب تغيّب والمعلم حضر — المعلم يستحق أجره كاملًا (قرار العميل).
        'student_no_show' => [
            'teacher' => 'full',
            'student' => 'consume',
        ],

        // غياب الطالب بعذر مقبول — المعلم حضر فيستحق أجره.
        'student_excused' => [
            'teacher' => 'full',
            'student' => 'keep',
        ],

        // اعتذار الطالب في الحصة الفردية: لا استحقاق لمعلم الحصة،
        // ويصبح عدم الاستحقاق خصمًا فقط لصاحب العقد الشهري.
        'individual_student_apology' => [
            'teacher' => 'deduct',
            'student' => 'keep',
        ],

        // إلغاء مقبول للحصة — يُخصم من المعلم ثمن حصة (قرار العميل).
        'cancelled_accepted' => [
            'teacher' => 'deduct',
            'student' => 'keep',
        ],

        // إلغاء متأخر من الطالب (بعد انقضاء المهلة) — يُعامل معاملة التغيّب.
        'cancelled_late_by_student' => [
            'teacher' => 'full',
            'student' => 'consume',
            'penalty' => 'student_late_cancellation',
        ],

        // المعلم تغيّب — يُخصم منه ثمن حصة، وتُعاد قيمتها للطالب إن كان دافعًا.
        'teacher_absent' => [
            'teacher' => 'deduct',
            'student' => 'refund',
        ],

        // اعتذار المعلم المعتمد: لا أجر للحصة، وخصم للعقد الشهري فقط
        // وفق خريطة أثر أساس العقد أدناه.
        'teacher_apology' => [
            'teacher' => 'deduct',
            'student' => 'keep',
        ],

        // الحصة مؤجَّلة — لا تُحتسب للمعلم إلا عند إقامتها فعلًا (قرار العميل).
        'postponed' => [
            'teacher' => 'deferred',
            'student' => 'keep',
        ],

        // حصة تلافي أُقيمت — تُحرَّر المستحقات المؤجَّلة.
        'makeup_completed' => [
            'teacher' => 'full',
            'student' => 'consume',
            'releases_deferred' => true,
        ],

        // عطلة رسمية أو إلغاء من المؤسسة — لا خصم على أحد.
        'cancelled_by_school' => [
            'teacher' => 'none',
            'student' => 'keep',
        ],
    ],

    /*
     * المعلم البديل.
     *
     * قرار العميل: تُحتسب الحصة للبديل بأجره هو (قد يختلف عن الأساسي)،
     * ويُخصم من المعلم الأساسي ثمن حصة.
     */
    'substitution' => [
        'substitute_rate_source' => 'own_contract',
        'primary_teacher_outcome' => 'deduct',
        'requires_approval' => true,
        'approver_permission' => 'session.assign_substitute',
    ],

    /*
     * الأثر الفعلي حسب أساس العقد.
     *
     * معلم الحصة لا يُنشأ عليه دين عند عدم التنفيذ؛ فقط لا تُضاف له قيدة
     * استحقاق. أما صاحب الراتب الشهري فيُخصم منه سعر حصة. إبقاء هذه
     * المصفوفة في الإعدادات يمنع تثبيت سياسة المدرسة داخل المستمع.
     */
    'contract_basis_effects' => [
        'per_session' => [
            'full' => 'full',
            'deduct' => 'none',
            'deferred' => 'deferred',
        ],
        'salary' => [
            'full' => 'none',
            'deduct' => 'deduct',
            'deferred' => 'none',
        ],
        'hybrid' => [
            'full' => 'full',
            'deduct' => 'deduct',
            'deferred' => 'deferred',
        ],
    ],

    /*
     * عند غياب سعر حصة مستقل في العقد الشهري، قيمة الحصة =
     * الراتب الأساسي ÷ الهدف الشهري للحصص، بالتقريب لأقرب وحدة صغرى؛
     * نصف الوحدة يُقرّب إلى أعلى بحساب أعداد صحيحة.
     */
    'salary_session_value' => [
        'enabled' => true,
    ],

    'teacher_apology' => [
        'approved_outcome' => 'teacher_apology',
    ],

    'student_apology' => [
        'applies_to_status' => 'excused',
        'individual_outcome' => 'individual_student_apology',
    ],

    /*
     * الموظف/المعلم بالراتب الثابت.
     *
     * قرار العميل: عليه مهام إدارية ومهام تدريبية بأعداد محددة في عقده.
     * المطلوب: إدراج الراتب في سجل الالتزامات، وإظهار الإنجاز، وإتاحة
     * الخصم والمكافأة — وليس ربط الراتب آليًا بعدد الحصص.
     */
    'salaried' => [
        'posts_monthly_obligation' => true,
        'tracks_targets' => ['teaching_sessions', 'administrative_tasks', 'training_sessions'],
        // الراتب لا ينقص آليًا عند النقص في الهدف — يظهر تنبيه للمشرف فقط.
        'auto_prorate_on_shortfall' => false,
        'shortfall_alert_threshold_percent' => 80,
    ],

    /*
     * المكافآت والخصومات.
     *
     * قرار العميل: الإدراج متاح لمشرفين بصلاحيات معيّنة مع كتابة ملحوظة،
     * والاعتماد لا يتم إلا من مشرف بصلاحية أعلى.
     */
    'adjustments' => [
        'types' => ['bonus', 'deduction', 'correction', 'advance', 'reimbursement'],
        'propose_permission' => 'payroll.adjustment.propose',
        'approve_permission' => 'payroll.adjustment.approve',
        'requires_note' => true,
        'requires_different_approver' => true, // من يقترح لا يعتمد
        'max_percent_of_period_without_escalation' => 25,
    ],

    /*
     * دورة فترة الرواتب.
     *
     * open → calculating → under_review → approved → paid → locked
     * بعد paid لا يُعدَّل التاريخ؛ أي تصحيح يصبح قيدة في الفترة التالية.
     */
    'period' => [
        'cycle' => 'monthly',
        'starts_on_day' => 1,
        // إقفال احتساب الفترة بعد انتهاء الشهر بهذه الأيام (لالتقاط حصص التلافي).
        'grace_days' => 3,
        'lock_after_payment' => true,
        'requires_review_before_approval' => true,
        'review_permission' => 'payroll.review',
        'approve_permission' => 'payroll.approve',
        'pay_permission' => 'payroll.pay',
    ],

    /*
     * ما الذي يُفعّل إنشاء القيود.
     * القيدة تُنشأ عند إقفال الحصة (finalized) وليس عند إنشائها.
     */
    'accrual_trigger' => 'session_finalized',
    'ledger_is_append_only' => true,

    /*
     * ══════════════════════════════════════════════════════════════════════
     * من حالة الحصة إلى مفتاح النتيجة.
     *
     * هذه الخريطة هي ما يحوّل حدث دورة حياة الحصة إلى صف في مصفوفة
     * `outcomes` أعلاه. وجودها هنا — لا داخل المستمع — يعني أن تغيير سياسة
     * المدرسة (مثلًا: ألّا يُخصم من المعلم عند إلغاء مقبول) تعديل سطر في
     * الإعدادات لا تعديل كود.
     *
     * المفتاح قيمة `SessionStatus`، والقيمة مفتاح في `outcomes`.
     * الحالة غير المذكورة هنا لا تولّد قيدة إطلاقًا.
     * ══════════════════════════════════════════════════════════════════════
     */
    'status_outcomes' => [
        'completed' => 'completed',
        'no_show' => 'student_no_show',
        'excused' => 'student_excused',
        'postponed' => 'postponed',
        'cancelled_by_student' => 'cancelled_accepted',
        'cancelled_by_teacher' => 'teacher_absent',
        'cancelled_by_school' => 'cancelled_by_school',
    ],

    /*
     * الحصة المكتملة التي جاءت تلافيًا لحصة مؤجَّلة تُعامل معاملة
     * `makeup_completed` لا `completed`، لأنها تُحرّر المستحق المؤجَّل.
     */
    'makeup_outcome' => 'makeup_completed',

    /*
     * إلغاء الطالب بعد انقضاء مهلة الإلغاء يُعامل معاملة التغيّب.
     * المهلة نفسها مملوكة لـ`config/scheduling.php` ولا تُكرَّر هنا.
     */
    'late_cancellation' => [
        'enabled' => true,
        'applies_to_status' => 'cancelled_by_student',
        'outcome' => 'cancelled_late_by_student',
        'deadline_config_key' => 'scheduling.notice.cancellation_minutes',
    ],

    /*
     * نوع القيدة حسب أثرها على المعلم.
     * القيمة تُخزَّن في العمود `entry_type` وتُستعمل في القيد الفريد
     * (session_id, staff_profile_id, entry_type) الذي يمنع التكرار.
     */
    'entry_types' => [
        'full' => 'session_earning',
        'deduct' => 'session_deduction',
        'deferred' => 'session_earning',
    ],
];
